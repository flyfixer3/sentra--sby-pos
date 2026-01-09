<?php

namespace Modules\Transfer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;

use Modules\Product\Entities\ProductDefectItem;
use Modules\Product\Entities\ProductDamagedItem;

use Modules\Transfer\Entities\TransferRequest;
use Modules\Transfer\Entities\TransferRequestItem;
use Modules\Transfer\Entities\PrintLog;

use Modules\Setting\Entities\Setting;
use Modules\Product\Entities\Warehouse;
use Modules\Product\Entities\Product;
use Modules\Mutation\Entities\Mutation;
use Modules\Mutation\Http\Controllers\MutationController;

use Modules\Branch\Entities\Branch;

class TransferController extends Controller
{
    private MutationController $mutationController;

    public function __construct(MutationController $mutationController)
    {
        $this->mutationController = $mutationController;
    }

    protected function activeBranch(): mixed
    {
        return session('active_branch');
    }

    protected function activeBranchIdOrFail(): int
    {
        $active = $this->activeBranch();

        if ($active === 'all' || $active === null || $active === '') {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                redirect()
                    ->route('transfers.index')
                    ->with('error', "Please choose a specific branch first (not 'All Branch') to create a transfer.")
            );
        }

        return (int) $active;
    }

    /**
     * ✅ Helper: pastikan user sedang ada di branch pengirim (outgoing owner)
     * - incoming branch tidak boleh print
     * - all branch tidak boleh print
     */
    private function assertSenderBranchOrAbort(TransferRequest $transfer): void
    {
        $active = $this->activeBranch();

        if ($active === 'all' || $active === null || $active === '') {
            abort(422, "Please choose a specific branch (not 'All Branch').");
        }

        if ((int) $active !== (int) $transfer->branch_id) {
            abort(403, 'Unauthorized. Only sender branch can print delivery note.');
        }
    }

    public function create()
    {
        abort_if(Gate::denies('access_transfers'), 403);

        $active = session('active_branch');
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('transfers.index')
                ->with('error', "Please choose a specific branch first (not 'All Branch') to create a transfer.");
        }

        $branchId = (int) $active;

        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderBy('warehouse_name')
            ->get();

        // cuma untuk display (UI)
        $last = TransferRequest::withoutGlobalScopes()->orderByDesc('id')->first();
        $nextNumber = $last ? ((int)$last->id + 1) : 1;
        $reference = make_reference_id('TRF', $nextNumber);

        return view('transfer::create', compact('warehouses', 'reference'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_transfers'), 403);

        $branchId = $this->activeBranchIdOrFail();

        $request->validate([
            'reference'          => 'nullable|string|max:50',
            'date'               => 'required|date',
            'from_warehouse_id'  => 'required|exists:warehouses,id',
            'to_branch_id'       => 'required|exists:branches,id|different:' . $branchId,
            'note'               => 'nullable|string|max:1000',

            'product_ids'        => 'required|array|min:1',
            'product_ids.*'      => 'required|integer|exists:products,id',

            'conditions'         => 'required|array|min:1',
            'conditions.*'       => 'required|string|in:good,defect,damaged',

            'quantities'         => 'required|array|min:1',
            'quantities.*'       => 'required|integer|min:1',
        ]);

        if (
            count($request->product_ids) !== count($request->quantities) ||
            count($request->product_ids) !== count($request->conditions)
        ) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Product, condition, and quantity counts do not match.');
        }

        $fromWarehouse = Warehouse::findOrFail($request->from_warehouse_id);
        if ((int) $fromWarehouse->branch_id !== $branchId) {
            abort(403, 'From Warehouse must belong to active branch.');
        }

        // ✅ VALIDASI STOCK PER CONDITION (GOOD / DEFECT / DAMAGED)
        foreach ($request->product_ids as $i => $productId) {
            $pid = (int) $productId;
            $qty = (int) $request->quantities[$i];
            $cond = strtolower((string) $request->conditions[$i]);

            // total stock dari Stock table (source of truth)
            $totalAvailable = (int) \Modules\Inventory\Entities\Stock::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('warehouse_id', (int) $request->from_warehouse_id)
                ->where('product_id', $pid)
                ->value('qty_available');

            if ($totalAvailable < 0) $totalAvailable = 0;

            // defect qty (yang belum moved out)
            $defectQty = (int) DB::table('product_defect_items')
                ->where('branch_id', $branchId)
                ->where('warehouse_id', (int) $request->from_warehouse_id)
                ->where('product_id', $pid)
                ->whereNull('moved_out_at')
                ->sum('quantity');

            // damaged qty (pending only + belum moved out)
            $damagedQty = (int) DB::table('product_damaged_items')
                ->where('branch_id', $branchId)
                ->where('warehouse_id', (int) $request->from_warehouse_id)
                ->where('product_id', $pid)
                ->where('resolution_status', 'pending')
                ->whereNull('moved_out_at')
                ->sum('quantity');

            if ($defectQty < 0) $defectQty = 0;
            if ($damagedQty < 0) $damagedQty = 0;

            // good qty = total - defect - damaged (seperti StocksDataTable kamu)
            $goodQty = $totalAvailable - $defectQty - $damagedQty;
            if ($goodQty < 0) $goodQty = 0;

            $availableByCond = match ($cond) {
                'good'   => $goodQty,
                'defect' => $defectQty,
                'damaged'=> $damagedQty,
                default  => 0,
            };

            if ($qty > $availableByCond) {
                $p = Product::find($pid);
                $name = $p ? ($p->product_name . ' | ' . $p->product_code) : ('Product ID ' . $pid);

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "Stock not enough for: {$name} ({$cond}). Requested: {$qty}, Available: {$availableByCond}.");
            }
        }

        DB::transaction(function () use ($request, $branchId) {

            $last = TransferRequest::withoutGlobalScopes()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();

            $nextNumber = $last ? ((int)$last->id + 1) : 1;
            $ref = make_reference_id('TRF', $nextNumber);

            $transfer = TransferRequest::create([
                'reference'          => $ref,
                'date'               => $request->date,
                'from_warehouse_id'  => (int) $request->from_warehouse_id,
                'to_branch_id'       => (int) $request->to_branch_id,
                'note'               => $request->note,
                'status'             => 'pending',
                'branch_id'          => $branchId,
                'created_by'         => auth()->id(),
                'delivery_code'      => null,
            ]);

            foreach ($request->product_ids as $i => $productId) {
                TransferRequestItem::create([
                    'transfer_request_id' => $transfer->id,
                    'product_id'          => (int) $productId,
                    'condition'           => strtolower((string) $request->conditions[$i]),
                    'quantity'            => (int) $request->quantities[$i],
                ]);
            }
        });

        toast('Transfer created successfully', 'success');
        return redirect()->route('transfers.index');
    }

    public function show($id)
    {
        abort_if(Gate::denies('show_transfers'), 403);

        $transfer = TransferRequest::withoutGlobalScopes()
            ->with([
                'fromWarehouse', 'toWarehouse', 'toBranch',
                'creator', 'confirmedBy',
                'items.product',
                'printLogs.user',
            ])
            ->findOrFail($id);

        // Ambil defect utk transfer ini
        $defects = ProductDefectItem::query()
            ->where('reference_type', TransferRequest::class)
            ->where('reference_id', (int) $transfer->id)
            ->orderBy('id', 'asc')
            ->get();

        // Ambil semua issue (damaged + missing) utk transfer ini
        $issues = ProductDamagedItem::query()
            ->where('reference_type', TransferRequest::class)
            ->where('reference_id', (int) $transfer->id)
            ->orderBy('id', 'asc')
            ->get();

        // split issue
        $damaged = $issues->where('damage_type', 'damaged')->values();
        $missing = $issues->where('damage_type', 'missing')->values();

        // Grouping per product_id
        $defectQtyByProduct = $defects->groupBy('product_id')->map(function ($rows) {
            return (int) $rows->sum('quantity');
        })->toArray();

        $damagedQtyByProduct = $damaged->groupBy('product_id')->map(function ($rows) {
            return (int) $rows->sum('quantity');
        })->toArray();

        $missingQtyByProduct = $missing->groupBy('product_id')->map(function ($rows) {
            return (int) $rows->sum('quantity');
        })->toArray();

        // Summary per item: sent, received_good, defect, damaged, missing
        $itemSummaries = [];
        $totalDefect = 0;
        $totalDamaged = 0;
        $totalMissing = 0;

        foreach ($transfer->items as $item) {
            $pid  = (int) $item->product_id;
            $sent = (int) $item->quantity;

            $defectQty  = (int) ($defectQtyByProduct[$pid] ?? 0);
            $damagedQty = (int) ($damagedQtyByProduct[$pid] ?? 0);
            $missingQty = (int) ($missingQtyByProduct[$pid] ?? 0);

            $receivedGood = $sent - $defectQty - $damagedQty - $missingQty;
            if ($receivedGood < 0) {
                $receivedGood = 0;
            }

            $totalDefect  += $defectQty;
            $totalDamaged += $damagedQty;
            $totalMissing += $missingQty;

            $itemSummaries[$pid] = [
                'sent'          => $sent,
                'received_good' => $receivedGood,
                'defect'        => $defectQty,
                'damaged'       => $damagedQty,
                'missing'       => $missingQty,
            ];
        }

        return view('transfer::show', compact(
            'transfer',
            'defects',
            'issues',
            'damaged',
            'missing',
            'itemSummaries',
            'totalDefect',
            'totalDamaged',
            'totalMissing'
        ));
    }

    /**
     * ✅ NEW: Prepare Print (AJAX)
     * - HANYA pengirim bisa print
     * - Print pertama: set shipped + generate delivery_code + mutation OUT (sekali saja)
     * - Setiap print: create PrintLog
     * - Return JSON: pdf_url + copy_number + status
     */
    public function preparePrint(int $id)
    {
        abort_if(Gate::denies('print_transfers'), 403);

        $transfer = TransferRequest::withoutGlobalScopes()
            ->with(['items.product', 'fromWarehouse', 'toBranch'])
            ->findOrFail($id);

        $this->assertSenderBranchOrAbort($transfer);

        // kalau sudah cancelled, jangan bisa print
        $rawStatus = strtolower(trim((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending')));
        if ($rawStatus === 'cancelled') {
            abort(422, 'Transfer is cancelled.');
        }

        $user = Auth::user();

        DB::transaction(function () use ($transfer, $user) {

            // FIRST PRINT: set shipped + delivery_code + printed_at/by + mutation OUT
            if (!$transfer->printed_at) {

                $code = $transfer->delivery_code ?: $this->generateUniqueDeliveryCode();

                $transfer->update([
                    'printed_at'    => now(),
                    'printed_by'    => $user->id,
                    'status'        => 'shipped',
                    'delivery_code' => $code,
                ]);

                // anti dobel OUT
                $alreadyOut = Mutation::withoutGlobalScopes()
                    ->where('reference', $transfer->reference)
                    ->where('note', 'like', 'Transfer OUT%')
                    ->exists();

                if (!$alreadyOut) {
                    foreach ($transfer->items as $item) {
                        $note = "Transfer OUT #{$transfer->reference} | From WH {$transfer->from_warehouse_id}";

                        $this->mutationController->applyInOut(
                            (int) $transfer->branch_id,
                            (int) $transfer->from_warehouse_id,
                            (int) $item->product_id,
                            'Out',
                            (int) $item->quantity,
                            (string) $transfer->reference,
                            $note,
                            (string) $transfer->getRawOriginal('date')
                        );

                        // ✅ NEW: kalau dari awal dia DEFECT / DAMAGED, maka tandai record kualitasnya "keluar"
                        $cond = strtolower((string) ($item->condition ?? 'good'));
                        $qty  = (int) $item->quantity;

                        if ($qty > 0 && in_array($cond, ['defect', 'damaged'], true)) {
                            if ($cond === 'defect') {
                                $ids = DB::table('product_defect_items')
                                    ->where('branch_id', (int) $transfer->branch_id)
                                    ->where('warehouse_id', (int) $transfer->from_warehouse_id)
                                    ->where('product_id', (int) $item->product_id)
                                    ->whereNull('moved_out_at')
                                    ->orderBy('id', 'asc')
                                    ->limit($qty)
                                    ->pluck('id')
                                    ->toArray();

                                if (count($ids) < $qty) {
                                    abort(422, "Defect stock not enough to ship for product ID {$item->product_id}. Need {$qty}, found ".count($ids).".");
                                }

                                DB::table('product_defect_items')
                                    ->whereIn('id', $ids)
                                    ->update([
                                        'moved_out_at' => now(),
                                        'moved_out_by' => $user->id,
                                        'moved_out_reference_type' => \Modules\Transfer\Entities\TransferRequest::class,
                                        'moved_out_reference_id' => (int) $transfer->id,
                                    ]);
                            }

                            if ($cond === 'damaged') {
                                $ids = DB::table('product_damaged_items')
                                    ->where('branch_id', (int) $transfer->branch_id)
                                    ->where('warehouse_id', (int) $transfer->from_warehouse_id)
                                    ->where('product_id', (int) $item->product_id)
                                    ->where('resolution_status', 'pending')
                                    ->whereNull('moved_out_at')
                                    ->orderBy('id', 'asc')
                                    ->limit($qty)
                                    ->pluck('id')
                                    ->toArray();

                                if (count($ids) < $qty) {
                                    abort(422, "Damaged stock not enough to ship for product ID {$item->product_id}. Need {$qty}, found ".count($ids).".");
                                }

                                DB::table('product_damaged_items')
                                    ->whereIn('id', $ids)
                                    ->update([
                                        'moved_out_at' => now(),
                                        'moved_out_by' => $user->id,
                                        'moved_out_reference_type' => \Modules\Transfer\Entities\TransferRequest::class,
                                        'moved_out_reference_id' => (int) $transfer->id,
                                    ]);
                            }
                        }
                    }
                }
            }

            // log print selalu
            PrintLog::create([
                'user_id'             => $user->id,
                'transfer_request_id' => $transfer->id,
                'printed_at'          => now(),
                'ip_address'          => request()->ip(),
            ]);
        });

        // hitung copy ke berapa (setelah log masuk)
        $copyNumber = (int) PrintLog::withoutGlobalScopes()
            ->where('transfer_request_id', (int) $transfer->id)
            ->count();

        // refresh transfer supaya status & delivery_code updated
        $transfer->refresh();

        return response()->json([
            'ok'          => true,
            'status'      => strtolower(trim((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending'))),
            'copy_number' => $copyNumber,
            'pdf_url'     => route('transfers.print.pdf', ['transfer' => $transfer->id, 'copy' => $copyNumber]),
        ]);
    }

    /**
     * ✅ Render PDF only (NO DB UPDATE)
     * - COPY number diambil dari query ?copy= (hasil preparePrint)
     * - address pakai branches.address & phone
     * - keterangan item: defect/damaged singkat dari DB (berdasarkan moved_out_reference_* saat preparePrint)
     * - notes dibuat PER ROW item (per transfer_request_items.id) supaya SKU sama tapi condition beda bisa dibedain
     * - GOOD juga ditulis keterangannya biar jelas
     */
    public function printPdf(Request $request, int $id)
    {
        abort_if(Gate::denies('print_transfers'), 403);

        $transfer = TransferRequest::withoutGlobalScopes()
            ->with(['items.product', 'fromWarehouse', 'toBranch'])
            ->findOrFail($id);

        $this->assertSenderBranchOrAbort($transfer);

        $rawStatus = strtolower(trim((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending')));
        if ($rawStatus === 'cancelled') {
            abort(422, 'Transfer is cancelled.');
        }

        $setting = Setting::first();

        $copyNumber = (int) ($request->query('copy') ?? 0);
        if ($copyNumber <= 0) {
            // fallback: count print logs (kalau user buka langsung GET)
            $copyNumber = (int) PrintLog::withoutGlobalScopes()
                ->where('transfer_request_id', (int) $transfer->id)
                ->count();

            if ($copyNumber <= 0) {
                $copyNumber = 1;
            }
        }

        $isReprint = $copyNumber > 1;

        // Branch data untuk address
        $senderBranch = Branch::withoutGlobalScopes()->find((int) $transfer->branch_id);
        $receiverBranch = Branch::withoutGlobalScopes()->find((int) $transfer->to_branch_id);

        // ===============================
        // ✅ Ambil defect/damaged berdasarkan moved_out_reference_* (OUTGOING)
        // supaya Surat Jalan bisa beda-bedain SKU yang sama tapi condition beda
        // ===============================

        // Defect yang DIKIRIM dari gudang pengirim (ditandai moved_out saat preparePrint)
        $movedDefects = ProductDefectItem::query()
            ->where('moved_out_reference_type', TransferRequest::class)
            ->where('moved_out_reference_id', (int) $transfer->id)
            ->orderBy('id', 'asc')
            ->get();

        // Damaged yang DIKIRIM dari gudang pengirim (ditandai moved_out saat preparePrint)
        $movedDamaged = ProductDamagedItem::query()
            ->where('moved_out_reference_type', TransferRequest::class)
            ->where('moved_out_reference_id', (int) $transfer->id)
            ->where('damage_type', 'damaged') // exclude missing
            ->orderBy('id', 'asc')
            ->get();

        $defectsByProduct = $movedDefects->groupBy('product_id');
        $damagedByProduct = $movedDamaged->groupBy('product_id');

        // helper truncate teks biar kolom "Keterangan" tidak kepanjangan
        $truncate = function (?string $text, int $max = 45): ?string {
            $text = trim((string) ($text ?? ''));
            if ($text === '') return null;
            if (mb_strlen($text) <= $max) return $text;
            return mb_substr($text, 0, $max) . '...';
        };

        // ✅ notes PER ITEM (per transfer_request_items.id)
        $notesByItemId = [];

        foreach ($transfer->items as $item) {
            $itemId = (int) $item->id;
            $pid    = (int) $item->product_id;
            $cond   = strtolower((string) ($item->condition ?? 'good'));
            $qty    = (int) $item->quantity;

            if ($qty <= 0) {
                $notesByItemId[$itemId] = '';
                continue;
            }

            $note = '';

            // ✅ GOOD: tampilkan keterangannya juga biar jelas
            if ($cond === 'good') {
                $note = 'GOOD';
            }

            // DEFECT: tampilkan defect_type + description singkat
            if ($cond === 'defect') {
                $rows = $defectsByProduct->get($pid, collect());

                $types = $rows->pluck('defect_type')->filter()->unique()->values()->take(3)->toArray();
                $typeText = !empty($types) ? implode(', ', $types) : 'Defect';

                $desc = $rows->pluck('description')->filter()->first();
                $desc = $truncate($desc, 45);

                $note = "DEFECT {$qty} ({$typeText})";
                if (!empty($desc)) {
                    $note .= " - {$desc}";
                }
            }

            // DAMAGED: tampilkan reason singkat
            if ($cond === 'damaged') {
                $rows = $damagedByProduct->get($pid, collect());

                $reason = $rows->pluck('reason')->filter()->first();
                $reason = $truncate($reason, 45);

                $note = "DAMAGED {$qty}";
                if (!empty($reason)) {
                    $note .= " - {$reason}";
                }
            }

            $notesByItemId[$itemId] = $note;
        }

        $pdf = Pdf::loadView('transfer::print', [
            'transfer'        => $transfer,
            'setting'         => $setting,
            'isReprint'       => $isReprint,
            'copyNumber'      => $copyNumber,
            'senderBranch'    => $senderBranch,
            'receiverBranch'  => $receiverBranch,
            'notesByItemId'   => $notesByItemId,
        ])->setPaper('A4', 'portrait');

        return $pdf->download("Surat_Jalan_{$transfer->reference}_COPY_{$copyNumber}.pdf");
    }

    public function showConfirmationForm(int $id)
    {
        abort_if(Gate::denies('confirm_transfers'), 403);

        $transfer = $this->findTransferOrFail($id);

        $active = $this->activeBranch();

        // ✅ All branch tidak boleh confirm
        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('transfers.index')
                ->with('error', "Please choose a specific branch first (not 'All Branch') to confirm a transfer.");
        }

        // ✅ hanya penerima yang boleh confirm
        if ((int) $transfer->to_branch_id !== (int) $active) {
            return redirect()
                ->route('transfers.show', $transfer->id)
                ->with('error', "You can't confirm this transfer because this branch is not the destination branch.");
        }

        $rawStatus = strtolower(trim((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending')));

        if ($rawStatus === 'cancelled') {
            return redirect()
                ->route('transfers.show', $transfer->id)
                ->with('error', "This transfer has been cancelled, so it can't be confirmed.");
        }

        // ✅ ini yang kamu minta: kalau masih pending, kasih pesan awam
        if ($rawStatus !== 'shipped') {
            // shipped = sudah dicetak surat jalan (status berubah saat first print)
            $friendly = match ($rawStatus) {
                'pending' => "This transfer is not ready to confirm yet. The sender hasn't printed the delivery note / shipped the transfer. Please ask the sender branch to print (Ship) first, then try again.",
                'confirmed' => "This transfer has already been confirmed.",
                'issue' => "This transfer has already been confirmed with issue.",
                default => "This transfer can't be confirmed right now (current status: {$rawStatus}).",
            };

            return redirect()
                ->route('transfers.show', $transfer->id)
                ->with('error', $friendly);
        }

        $warehouses = Warehouse::where('branch_id', (int) $active)->get();

        return view('transfer::confirm', compact('transfer', 'warehouses'));
    }

    /**
     * ✅ Jangan ubah logika missing/damaged/issue kamu (sesuai request)
     */
    public function storeConfirmation(Request $request, int $id)
    {
        abort_if(Gate::denies('confirm_transfers'), 403);

        $transfer = TransferRequest::withoutGlobalScopes()
            ->with(['items.product', 'fromWarehouse', 'toBranch'])
            ->findOrFail($id);

        $active = session('active_branch');

        if ($active === 'all' || $active === null || $active === '') {
            return redirect()
                ->route('transfers.index')
                ->with('error', "Please choose a specific branch first (not 'All Branch') to confirm a transfer.");
        }

        if ((int) $transfer->to_branch_id !== (int) $active) {
            return redirect()
                ->route('transfers.show', $transfer->id)
                ->with('error', "You can't confirm this transfer because this branch is not the destination branch.");
        }

        $rawStatus = strtolower(trim((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending')));

        if ($rawStatus === 'cancelled') {
            return redirect()
                ->route('transfers.show', $transfer->id)
                ->with('error', "This transfer has been cancelled, so it can't be confirmed.");
        }

        // ✅ Friendly message kalau belum shipped
        if ($rawStatus !== 'shipped') {
            $friendly = match ($rawStatus) {
                'pending'   => "This transfer is not ready to confirm yet. The sender hasn't printed the delivery note / shipped the transfer. Please ask the sender branch to print (Ship) first.",
                'confirmed' => "This transfer has already been confirmed.",
                'issue'     => "This transfer has already been confirmed with issue.",
                default     => "This transfer can't be confirmed right now (current status: {$rawStatus}).",
            };

            return redirect()
                ->route('transfers.show', $transfer->id)
                ->with('error', $friendly);
        }

        // ===============================
        // mulai sini: logic kamu BIARKAN
        // + ditambah upload photo handling
        // ===============================
        $request->validate([
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'delivery_code'   => 'required|string|size:6',
            'confirm_issue'   => 'nullable|in:0,1',

            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|integer',
            'items.*.qty_sent'      => 'required|integer|min:0',
            'items.*.condition'     => 'required|string|in:good,defect,damaged',

            'items.*.qty_received'  => 'required|integer|min:0',
            'items.*.qty_defect'    => 'required|integer|min:0',
            'items.*.qty_damaged'   => 'required|integer|min:0',

            // ✅ NEW: optional photo validation
            'items.*.defects.*.photo'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'items.*.damaged_items.*.photo'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            // (kalau suatu saat missing juga mau foto, siapin)
            'items.*.missing_items.*.photo'  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if (strtoupper($request->delivery_code) !== strtoupper((string) $transfer->delivery_code)) {
            abort(422, 'Invalid delivery code.');
        }

        // ✅ map condition + qty_sent asli dari DB (anti tamper)
        $dbMap = $transfer->items->map(function ($it) {
            return [
                'product_id' => (int) $it->product_id,
                'condition'  => strtolower((string) ($it->condition ?? 'good')),
                'qty_sent'   => (int) $it->quantity,
            ];
        })->values();

        DB::transaction(function () use ($request, $transfer, $dbMap) {

            $alreadyIn = Mutation::withoutGlobalScopes()
                ->where('reference', $transfer->reference)
                ->where('note', 'like', 'Transfer IN%')
                ->exists();

            if ($alreadyIn) {
                abort(422, 'Transfer already confirmed (stock movement exists).');
            }

            $hasIssue = false;

            foreach ($request->items as $row) {
                $productId = (int) $row['product_id'];
                $sent      = (int) $row['qty_sent'];
                $condForm  = strtolower((string) $row['condition']);

                $good    = (int) $row['qty_received'];
                $defect  = (int) $row['qty_defect'];
                $damaged = (int) $row['qty_damaged'];

                $total = $good + $defect + $damaged;

                if ($total > $sent) {
                    abort(422, "Invalid input: Good + Defect + Damaged cannot be greater than Sent.");
                }

                if ($condForm === 'defect' && $good > 0) {
                    abort(422, "Invalid input: Item sent as DEFECT cannot be received as GOOD.");
                }

                if ($condForm === 'damaged' && ($good > 0 || $defect > 0)) {
                    abort(422, "Invalid input: Item sent as DAMAGED cannot be received as GOOD/DEFECT.");
                }

                $exists = $dbMap->contains(function ($x) use ($productId, $condForm, $sent) {
                    return (int) $x['product_id'] === $productId
                        && (string) $x['condition'] === $condForm
                        && (int) $x['qty_sent'] === $sent;
                });

                if (!$exists) {
                    abort(422, "Invalid items payload (row mismatch). Please reload the page.");
                }

                $missing = $sent - $total;
                if ($missing > 0) $hasIssue = true;
            }

            if ($hasIssue && (string) $request->get('confirm_issue', '0') !== '1') {
                abort(422, "There is a remaining/missing quantity. Please confirm 'Complete with issue' to proceed.");
            }

            $transfer->update([
                'to_warehouse_id' => (int) $request->to_warehouse_id,
                'status'          => $hasIssue ? 'issue' : 'confirmed',
                'confirmed_by'    => auth()->id(),
                'confirmed_at'    => now(),
            ]);

            // ✅ IMPORTANT: pakai row index untuk ambil file uploads
            foreach ($request->items as $rowIndex => $row) {
                $productId   = (int) $row['product_id'];
                $qtySent     = (int) $row['qty_sent'];
                $qtyReceived = (int) $row['qty_received'];
                $qtyDefect   = (int) $row['qty_defect'];
                $qtyDamaged  = (int) $row['qty_damaged'];

                $totalIn = $qtyReceived + $qtyDefect + $qtyDamaged;

                if ($totalIn > 0) {
                    $this->mutationController->applyInOut(
                        (int) $transfer->to_branch_id,
                        (int) $request->to_warehouse_id,
                        $productId,
                        'In',
                        $totalIn,
                        $transfer->reference,
                        "Transfer IN #{$transfer->reference}",
                        now()->toDateString()
                    );
                }

                // =========================
                // DEFECT (create per unit)
                // + PHOTO upload save to photo_path
                // =========================
                if ($qtyDefect > 0) {
                    $defectsPayload = $row['defects'] ?? [];

                    foreach ($defectsPayload as $i => $d) {
                        $photoPath = null;

                        // items[rowIndex][defects][i][photo]
                        if ($request->hasFile("items.$rowIndex.defects.$i.photo")) {
                            $file = $request->file("items.$rowIndex.defects.$i.photo");
                            if ($file && $file->isValid()) {
                                $photoPath = $file->store('defects', 'public');
                            }
                        }

                        ProductDefectItem::create([
                            'branch_id'      => (int) $transfer->to_branch_id,
                            'warehouse_id'   => (int) $request->to_warehouse_id,
                            'product_id'     => $productId,
                            'reference_id'   => (int) $transfer->id,
                            'reference_type' => TransferRequest::class,
                            'quantity'       => 1,
                            'defect_type'    => $d['defect_type'] ?? null,
                            'description'    => $d['defect_description'] ?? null,
                            'photo_path'     => $photoPath, // ✅ save file path
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }

                // =========================
                // DAMAGED (create per unit)
                // + PHOTO upload save to photo_path
                // =========================
                if ($qtyDamaged > 0) {
                    $damagedPayload = $row['damaged_items'] ?? [];

                    foreach ($damagedPayload as $i => $d) {
                        $photoPath = null;

                        // items[rowIndex][damaged_items][i][photo]
                        if ($request->hasFile("items.$rowIndex.damaged_items.$i.photo")) {
                            $file = $request->file("items.$rowIndex.damaged_items.$i.photo");
                            if ($file && $file->isValid()) {
                                $photoPath = $file->store('damages', 'public');
                            }
                        }

                        ProductDamagedItem::create([
                            'branch_id'         => (int) $transfer->to_branch_id,
                            'warehouse_id'      => (int) $request->to_warehouse_id,
                            'product_id'        => $productId,
                            'reference_id'      => (int) $transfer->id,
                            'reference_type'    => TransferRequest::class,
                            'quantity'          => 1,
                            'damage_type'       => 'damaged',
                            'cause'             => 'transfer',
                            'resolution_status' => 'pending',
                            'reason'            => $d['damaged_reason'] ?? null,
                            'photo_path'        => $photoPath, // ✅ pastikan kolom ini ADA di product_damaged_items
                            'created_by'        => auth()->id(),
                            'mutation_in_id'    => null,
                            'mutation_out_id'   => null,
                        ]);
                    }
                }

                // =========================
                // MISSING (create per unit)
                // (photo optional kalau nanti kamu pakai)
                // =========================
                $missingQty = $qtySent - $totalIn;
                if ($missingQty > 0) {
                    $missingDetails = $row['missing_items'] ?? [];

                    for ($i = 0; $i < $missingQty; $i++) {
                        $detail = $missingDetails[$i] ?? [];

                        $photoPath = null;
                        if ($request->hasFile("items.$rowIndex.missing_items.$i.photo")) {
                            $file = $request->file("items.$rowIndex.missing_items.$i.photo");
                            if ($file && $file->isValid()) {
                                $photoPath = $file->store('missing', 'public');
                            }
                        }

                        ProductDamagedItem::create([
                            'branch_id'         => (int) $transfer->to_branch_id,
                            'warehouse_id'      => (int) $request->to_warehouse_id,
                            'product_id'        => $productId,
                            'reference_id'      => (int) $transfer->id,
                            'reference_type'    => TransferRequest::class,
                            'quantity'          => 1,
                            'damage_type'       => 'missing',
                            'cause'             => 'transfer',
                            'resolution_status' => 'pending',
                            'reason'            => $detail['missing_reason'] ?? 'Missing on confirmation',
                            'photo_path'        => $photoPath, // ✅ kalau kolom ini ada & kamu mau simpan
                            'created_by'        => auth()->id(),
                            'mutation_in_id'    => null,
                            'mutation_out_id'   => null,
                        ]);
                    }
                }
            }
        });

        toast('Transfer confirmed successfully', 'success');
        return redirect()->route('transfers.show', $transfer->id);
    }

    public function destroy($id)
    {
        abort_if(Gate::denies('delete_transfers'), 403);

        $transfer = TransferRequest::findOrFail($id);

        if (Mutation::where('reference', $transfer->reference)->exists()) {
            return response()->json([
                'message' => 'Cannot delete transfer with existing stock movements. Please cancel via proper reversal flow.'
            ], 422);
        }

        $transfer->delete();
        return response()->json(['message' => 'Transfer deleted successfully']);
    }

    public function cancel(Request $request, int $id)
    {
        abort_if(Gate::denies('cancel_transfers'), 403);

        $transfer = TransferRequest::withoutGlobalScopes()
            ->with(['items.product'])
            ->findOrFail($id);

        $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $status = strtolower(trim((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending')));

        if ($status === 'cancelled') abort(422, 'Transfer already cancelled.');

        if (!in_array($status, ['shipped', 'confirmed', 'issue'], true)) {
            abort(422, 'Only shipped/confirmed transfer can be cancelled.');
        }

        DB::transaction(function () use ($request, $transfer, $status) {

            $alreadyCancelled = Mutation::withoutGlobalScopes()
                ->where('reference', $transfer->reference)
                ->where('note', 'like', 'Transfer CANCEL%')
                ->exists();

            if ($alreadyCancelled) abort(422, 'Cancel mutation already exists for this transfer.');

            $cancelNote = trim((string) $request->note);

            // ✅ ONLY SHIPPED: revert moved_out markers, because stock is returning to sender WH
            if ($status === 'shipped') {

                DB::table('product_defect_items')
                    ->where('moved_out_reference_type', \Modules\Transfer\Entities\TransferRequest::class)
                    ->where('moved_out_reference_id', (int) $transfer->id)
                    ->update([
                        'moved_out_at' => null,
                        'moved_out_by' => null,
                        'moved_out_reference_type' => null,
                        'moved_out_reference_id' => null,
                    ]);

                DB::table('product_damaged_items')
                    ->where('moved_out_reference_type', \Modules\Transfer\Entities\TransferRequest::class)
                    ->where('moved_out_reference_id', (int) $transfer->id)
                    ->update([
                        'moved_out_at' => null,
                        'moved_out_by' => null,
                        'moved_out_reference_type' => null,
                        'moved_out_reference_id' => null,
                    ]);

                foreach ($transfer->items as $item) {
                    $qty = (int) $item->quantity;
                    if ($qty <= 0) continue;

                    $note = "Transfer CANCEL IN #{$transfer->reference} | Return to WH {$transfer->from_warehouse_id} | {$cancelNote}";

                    $this->mutationController->applyInOut(
                        (int) $transfer->branch_id,
                        (int) $transfer->from_warehouse_id,
                        (int) $item->product_id,
                        'In',
                        $qty,
                        (string) $transfer->reference,
                        $note,
                        (string) now()->toDateString()
                    );
                }
            }

            if (in_array($status, ['confirmed', 'issue'], true)) {

                if (!$transfer->to_warehouse_id) abort(422, 'Invalid transfer data: destination warehouse is empty.');

                $damagedQtyByProduct = ProductDamagedItem::query()
                    ->where('reference_type', TransferRequest::class)
                    ->where('reference_id', (int) $transfer->id)
                    ->where('damage_type', 'damaged')
                    ->selectRaw('product_id, COALESCE(SUM(quantity),0) as qty')
                    ->groupBy('product_id')
                    ->pluck('qty', 'product_id')
                    ->toArray();

                $missingQtyByProduct = ProductDamagedItem::query()
                    ->where('reference_type', TransferRequest::class)
                    ->where('reference_id', (int) $transfer->id)
                    ->where('damage_type', 'missing')
                    ->selectRaw('product_id, COALESCE(SUM(quantity),0) as qty')
                    ->groupBy('product_id')
                    ->pluck('qty', 'product_id')
                    ->toArray();

                foreach ($transfer->items as $item) {
                    $pid  = (int) $item->product_id;
                    $sent = (int) $item->quantity;
                    if ($sent <= 0) continue;

                    $damaged = (int) ($damagedQtyByProduct[$pid] ?? 0);
                    $missing = (int) ($missingQtyByProduct[$pid] ?? 0);

                    if ($damaged < 0) $damaged = 0;
                    if ($missing < 0) $missing = 0;

                    if ($damaged > $sent) $damaged = $sent;
                    if ($missing > $sent) $missing = $sent;

                    $moveBackQty = $sent - $missing - $damaged;

                    if ($moveBackQty < 0) $moveBackQty = 0;
                    if ($moveBackQty > $sent) $moveBackQty = $sent;

                    if ($moveBackQty > 0) {
                        $noteOut = "Transfer CANCEL OUT #{$transfer->reference} | Return from WH {$transfer->to_warehouse_id} | {$cancelNote}";

                        $this->mutationController->applyInOut(
                            (int) $transfer->to_branch_id,
                            (int) $transfer->to_warehouse_id,
                            $pid,
                            'Out',
                            $moveBackQty,
                            (string) $transfer->reference,
                            $noteOut,
                            (string) now()->toDateString()
                        );

                        $noteIn = "Transfer CANCEL IN #{$transfer->reference} | Return to WH {$transfer->from_warehouse_id} | {$cancelNote}";

                        $this->mutationController->applyInOut(
                            (int) $transfer->branch_id,
                            (int) $transfer->from_warehouse_id,
                            $pid,
                            'In',
                            $moveBackQty,
                            (string) $transfer->reference,
                            $noteIn,
                            (string) now()->toDateString()
                        );
                    }
                }
            }

            $transfer->forceFill([
                'status'       => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancel_note'  => $cancelNote,
            ])->save();
        });

        toast('Transfer cancelled successfully', 'warning');

        $redirectTo = $request->input('redirect_to');
        if ($redirectTo) return redirect($redirectTo);

        return redirect()->route('transfers.index');
    }

    private function generateUniqueDeliveryCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        } while (
            TransferRequest::withoutGlobalScopes()
                ->where('delivery_code', $code)
                ->exists()
        );

        return $code;
    }

    private function findTransferOrFail(int $id): TransferRequest
    {
        return TransferRequest::withoutGlobalScopes()
            ->with([
                'fromWarehouse', 'toWarehouse', 'toBranch',
                'creator', 'confirmedBy',
                'items.product',
                'printLogs',
            ])
            ->findOrFail($id);
    }
}
