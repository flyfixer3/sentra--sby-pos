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

        // ini akan redirect ke index bila All Branch (via HttpResponseException)
        $branchId = $this->activeBranchIdOrFail();

        $request->validate([
            'reference'          => 'nullable|string|max:50',
            'date'               => 'required|date',
            'from_warehouse_id'  => 'required|exists:warehouses,id',
            'to_branch_id'       => 'required|exists:branches,id|different:' . $branchId,
            'note'               => 'nullable|string|max:1000',

            'product_ids'        => 'required|array|min:1',
            'product_ids.*'      => 'required|integer|exists:products,id',
            'quantities'         => 'required|array|min:1',
            'quantities.*'       => 'required|integer|min:1',
        ]);

        if (count($request->product_ids) !== count($request->quantities)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Product and quantity counts do not match.');
        }

        $fromWarehouse = Warehouse::findOrFail($request->from_warehouse_id);
        if ((int) $fromWarehouse->branch_id !== $branchId) {
            abort(403, 'From Warehouse must belong to active branch.');
        }

        // ✅ VALIDASI STOCK DARI GUDANG SUMBER
        foreach ($request->product_ids as $i => $productId) {
            $pid = (int) $productId;
            $qty = (int) $request->quantities[$i];

            $stockIn = (int) Mutation::withoutGlobalScopes()
                ->where('warehouse_id', (int) $request->from_warehouse_id)
                ->where('product_id', $pid)
                ->sum('stock_in');

            $stockOut = (int) Mutation::withoutGlobalScopes()
                ->where('warehouse_id', (int) $request->from_warehouse_id)
                ->where('product_id', $pid)
                ->sum('stock_out');

            $available = $stockIn - $stockOut;
            if ($available < 0) $available = 0;

            if ($qty > $available) {
                $p = Product::find($pid);
                $name = $p ? ($p->product_name . ' | ' . $p->product_code) : ('Product ID ' . $pid);

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', "Stock not enough for: {$name}. Requested: {$qty}, Available in selected warehouse: {$available}.");
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
     * - keterangan item: defect/damaged singkat dari DB
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

        // Ambil defect utk transfer ini (buat notes per product)
        $defects = ProductDefectItem::query()
            ->where('reference_type', TransferRequest::class)
            ->where('reference_id', (int) $transfer->id)
            ->orderBy('id', 'asc')
            ->get();

        // Ambil damaged utk transfer ini (damage_type=damaged saja, exclude missing)
        $damaged = ProductDamagedItem::query()
            ->where('reference_type', TransferRequest::class)
            ->where('reference_id', (int) $transfer->id)
            ->where('damage_type', 'damaged')
            ->orderBy('id', 'asc')
            ->get();

        // Build short note per product_id
        $notesByProduct = [];

        $defectsByProduct = $defects->groupBy('product_id');
        $damagedByProduct = $damaged->groupBy('product_id');

        $allProductIds = collect(array_merge(
            array_keys($defectsByProduct->toArray()),
            array_keys($damagedByProduct->toArray())
        ))->unique()->values();

        foreach ($allProductIds as $pid) {
            $pid = (int) $pid;

            $parts = [];

            if (isset($defectsByProduct[$pid])) {
                $rows = $defectsByProduct[$pid];

                $types = $rows->pluck('defect_type')->filter()->unique()->values()->take(3)->toArray();
                $desc  = $rows->pluck('description')->filter()->first();

                $qtyDef = (int) $rows->sum('quantity');
                $t = !empty($types) ? implode(', ', $types) : 'Defect';
                $text = "DEFECT {$qtyDef} ({$t})";

                if (!empty($desc)) {
                    $desc = trim((string) $desc);
                    if (mb_strlen($desc) > 40) $desc = mb_substr($desc, 0, 40) . '...';
                    $text .= " - {$desc}";
                }

                $parts[] = $text;
            }

            if (isset($damagedByProduct[$pid])) {
                $rows = $damagedByProduct[$pid];

                $qtyDmg = (int) $rows->sum('quantity');
                $reason = $rows->pluck('reason')->filter()->first();

                $text = "DAMAGED {$qtyDmg}";
                if (!empty($reason)) {
                    $reason = trim((string) $reason);
                    if (mb_strlen($reason) > 40) $reason = mb_substr($reason, 0, 40) . '...';
                    $text .= " - {$reason}";
                }

                $parts[] = $text;
            }

            if (!empty($parts)) {
                $notesByProduct[$pid] = implode(' | ', $parts);
            }
        }

        $pdf = Pdf::loadView('transfer::print', [
            'transfer'        => $transfer,
            'setting'         => $setting,
            'isReprint'       => $isReprint,
            'copyNumber'      => $copyNumber,
            'senderBranch'    => $senderBranch,
            'receiverBranch'  => $receiverBranch,
            'notesByProduct'  => $notesByProduct,
        ])->setPaper('A4', 'portrait');

        return $pdf->download("Surat_Jalan_{$transfer->reference}_COPY_{$copyNumber}.pdf");
    }

    public function showConfirmationForm(int $id)
    {
        abort_if(Gate::denies('confirm_transfers'), 403);

        $transfer = $this->findTransferOrFail($id);

        $active = $this->activeBranch();
        if ($active === 'all') abort(422, "Please choose a specific branch (not 'All Branch') to confirm incoming transfer.");
        if ((int) $transfer->to_branch_id !== (int) $active) abort(403, 'Unauthorized.');

        if ($transfer->status === 'cancelled') abort(422, 'Transfer already cancelled.');
        if ($transfer->status !== 'shipped') abort(422, 'Only shipped transfer can be confirmed.');

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
        if ($active === 'all') abort(422, "Please choose a specific branch.");
        if ((int) $transfer->to_branch_id !== (int) $active) abort(403, 'Unauthorized.');
        if ($transfer->status !== 'shipped') abort(422, 'Only shipped transfer can be confirmed.');

        $request->validate([
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'delivery_code'   => 'required|string|size:6',
            'confirm_issue'   => 'nullable|in:0,1',

            'items'                 => 'required|array|min:1',
            'items.*.product_id'    => 'required|integer',
            'items.*.qty_sent'      => 'required|integer|min:0',
            'items.*.qty_received'  => 'required|integer|min:0',
            'items.*.qty_defect'    => 'required|integer|min:0',
            'items.*.qty_damaged'   => 'required|integer|min:0',
        ]);

        if (strtoupper($request->delivery_code) !== strtoupper((string) $transfer->delivery_code)) {
            abort(422, 'Invalid delivery code.');
        }

        DB::transaction(function () use ($request, $transfer) {

            $alreadyIn = Mutation::withoutGlobalScopes()
                ->where('reference', $transfer->reference)
                ->where('note', 'like', 'Transfer IN%')
                ->exists();

            if ($alreadyIn) {
                abort(422, 'Transfer already confirmed (stock movement exists).');
            }

            $hasIssue = false;

            foreach ($request->items as $row) {
                $sent    = (int) $row['qty_sent'];
                $good    = (int) $row['qty_received'];
                $defect  = (int) $row['qty_defect'];
                $damaged = (int) $row['qty_damaged'];

                $total = $good + $defect + $damaged;

                if ($total > $sent) {
                    abort(422, "Invalid input: Good + Defect + Damaged cannot be greater than Sent.");
                }

                $missing = $sent - $total;
                if ($missing > 0) $hasIssue = true;
            }

            if ($hasIssue && (string) $request->get('confirm_issue', '0') !== '1') {
                abort(422, "There is a remaining/missing quantity. Please confirm 'Complete with issue' to proceed.");
            }

            $transfer->update([
                'to_warehouse_id' => (int) $request->to_warehouse_id,
                'status' => $hasIssue ? 'issue' : 'confirmed',
                'confirmed_by'    => auth()->id(),
                'confirmed_at'    => now(),
            ]);

            foreach ($request->items as $row) {

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

                if ($qtyDefect > 0) {
                    foreach (($row['defects'] ?? []) as $d) {
                        ProductDefectItem::create([
                            'branch_id'      => (int) $transfer->to_branch_id,
                            'warehouse_id'   => (int) $request->to_warehouse_id,
                            'product_id'     => $productId,
                            'reference_id'   => (int) $transfer->id,
                            'reference_type' => TransferRequest::class,
                            'quantity'       => 1,
                            'defect_type'    => $d['defect_type'] ?? null,
                            'description'    => $d['defect_description'] ?? null,
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }

                if ($qtyDamaged > 0) {
                    foreach (($row['damaged_items'] ?? []) as $d) {
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
                            'created_by'        => auth()->id(),
                            'mutation_in_id'    => null,
                            'mutation_out_id'   => null,
                        ]);
                    }
                }

                $missingQty = $qtySent - $totalIn;
                if ($missingQty > 0) {

                    $missingDetails = $row['missing_items'] ?? [];

                    for ($i = 0; $i < $missingQty; $i++) {
                        $detail = $missingDetails[$i] ?? [];

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

        if (!in_array($status, ['shipped', 'confirmed', 'confirmed_issue'], true)) {
            abort(422, 'Only shipped/confirmed transfer can be cancelled.');
        }

        DB::transaction(function () use ($request, $transfer, $status) {

            $alreadyCancelled = Mutation::withoutGlobalScopes()
                ->where('reference', $transfer->reference)
                ->where('note', 'like', 'Transfer CANCEL%')
                ->exists();

            if ($alreadyCancelled) abort(422, 'Cancel mutation already exists for this transfer.');

            $cancelNote = trim((string) $request->note);

            if ($status === 'shipped') {
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

            if (in_array($status, ['confirmed', 'confirmed_issue'], true)) {

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
