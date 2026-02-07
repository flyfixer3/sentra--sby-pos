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
use Modules\Inventory\Entities\Rack;
use Modules\Inventory\Entities\StockRack;

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

    private function normalizeStatus(string $status): string
    {
        return strtolower(trim((string) $status));
    }

    private function assertRackBelongsToWarehouse(int $rackId, int $warehouseId): void
    {
        $ok = Rack::query()
            ->where('id', $rackId)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        if (!$ok) {
            throw new \RuntimeException("Invalid rack: rack_id={$rackId} does not belong to warehouse_id={$warehouseId}.");
        }
    }

    /**
     * Adjust StockRack (can be + or -).
     * qtyAvailable follows total move (good+defect+damaged).
     */
    private function adjustStockRack(
        int $branchId,
        int $warehouseId,
        int $rackId,
        int $productId,
        int $deltaAll,
        int $deltaGood,
        int $deltaDefect,
        int $deltaDamaged
    ): void {
        $row = StockRack::withoutGlobalScopes()->firstOrNew([
            'branch_id'    => $branchId,
            'warehouse_id' => $warehouseId,
            'rack_id'      => $rackId,
            'product_id'   => $productId,
        ]);

        $row->qty_available = (int) ($row->qty_available ?? 0);
        $row->qty_good      = (int) ($row->qty_good ?? 0);
        $row->qty_defect    = (int) ($row->qty_defect ?? 0);
        $row->qty_damaged   = (int) ($row->qty_damaged ?? 0);

        $row->qty_available += $deltaAll;
        $row->qty_good      += $deltaGood;
        $row->qty_defect    += $deltaDefect;
        $row->qty_damaged   += $deltaDamaged;

        // clamp min 0
        if ($row->qty_available < 0) $row->qty_available = 0;
        if ($row->qty_good < 0)      $row->qty_good = 0;
        if ($row->qty_defect < 0)    $row->qty_defect = 0;
        if ($row->qty_damaged < 0)   $row->qty_damaged = 0;

        $row->save();
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

        // ✅ Racks per warehouse (untuk dropdown FROM rack) - ini optional untuk UI biasa,
        // tapi Livewire kita sekarang query langsung dari stock_racks.
        $racksByWarehouse = Rack::query()
            ->whereIn('warehouse_id', $warehouses->pluck('id')->map(fn($x)=>(int)$x)->toArray())
            ->orderByRaw("CASE WHEN code IS NULL OR code = '' THEN 1 ELSE 0 END ASC")
            ->orderBy('code')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->groupBy('warehouse_id');

        $last = TransferRequest::withoutGlobalScopes()->orderByDesc('id')->first();
        $nextNumber = $last ? ((int)$last->id + 1) : 1;
        $reference = make_reference_id('TRF', $nextNumber);

        return view('transfer::create', compact('warehouses', 'reference', 'racksByWarehouse'));
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

            // ✅ NEW: from rack per row item
            'from_rack_ids'      => 'required|array|min:1',
            'from_rack_ids.*'    => 'required|integer|min:1',
        ]);

        if (
            count($request->product_ids) !== count($request->quantities) ||
            count($request->product_ids) !== count($request->conditions) ||
            count($request->product_ids) !== count($request->from_rack_ids)
        ) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Product, condition, quantity, and rack counts do not match.');
        }

        $fromWarehouse = Warehouse::findOrFail($request->from_warehouse_id);
        if ((int) $fromWarehouse->branch_id !== $branchId) {
            abort(403, 'From Warehouse must belong to active branch.');
        }

        // ✅ VALIDASI rack harus milik from_warehouse
        foreach ($request->from_rack_ids as $i => $rackId) {
            $rid = (int) $rackId;
            $this->assertRackBelongsToWarehouse($rid, (int) $request->from_warehouse_id);
        }

        // ✅ VALIDASI STOCK PER CONDITION (GOOD / DEFECT / DAMAGED) (punya kamu, keep)
        foreach ($request->product_ids as $i => $productId) {
            $pid = (int) $productId;
            $qty = (int) $request->quantities[$i];
            $cond = strtolower((string) $request->conditions[$i]);

            $totalAvailable = (int) \Modules\Inventory\Entities\Stock::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('warehouse_id', (int) $request->from_warehouse_id)
                ->where('product_id', $pid)
                ->value('qty_available');

            if ($totalAvailable < 0) $totalAvailable = 0;

            $defectQty = (int) DB::table('product_defect_items')
                ->where('branch_id', $branchId)
                ->where('warehouse_id', (int) $request->from_warehouse_id)
                ->where('product_id', $pid)
                ->whereNull('moved_out_at')
                ->sum('quantity');

            $damagedQty = (int) DB::table('product_damaged_items')
                ->where('branch_id', $branchId)
                ->where('warehouse_id', (int) $request->from_warehouse_id)
                ->where('product_id', $pid)
                ->where('resolution_status', 'pending')
                ->whereNull('moved_out_at')
                ->sum('quantity');

            if ($defectQty < 0) $defectQty = 0;
            if ($damagedQty < 0) $damagedQty = 0;

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
                    'from_rack_id'        => (int) $request->from_rack_ids[$i], // ✅ NEW
                    'condition'           => strtolower((string) $request->conditions[$i]),
                    'quantity'            => (int) $request->quantities[$i],
                    'to_rack_id'          => null, // akan diisi saat receiver confirm
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

        // Ambil defect utk transfer ini (incoming side record)
        $defects = ProductDefectItem::query()
            ->where('reference_type', TransferRequest::class)
            ->where('reference_id', (int) $transfer->id)
            ->orderBy('id', 'asc')
            ->get();

        // Ambil semua issue (damaged + missing) utk transfer ini (incoming side record)
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
            if ($receivedGood < 0) $receivedGood = 0;

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

        // ===========================
        // ✅ RACK MOVEMENT LOGS
        // ===========================
        $rackLogsOutgoing = [];
        $rackLogsIncoming = [];

        // status normalized (biar konsisten dengan blade kamu)
        $status = strtolower(trim((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending')));

        // collect rack ids used
        $rackIds = [];

        foreach ($transfer->items as $it) {
            if (!empty($it->from_rack_id)) $rackIds[] = (int) $it->from_rack_id;
            if (!empty($it->to_rack_id))   $rackIds[] = (int) $it->to_rack_id;
        }

        foreach ($defects as $d) {
            if (!empty($d->rack_id)) $rackIds[] = (int) $d->rack_id;
        }

        foreach ($issues as $is) {
            if (!empty($is->rack_id)) $rackIds[] = (int) $is->rack_id;
        }

        $rackIds = array_values(array_unique(array_filter($rackIds)));

        $rackMap = [];
        if (!empty($rackIds)) {
            $rackMap = Rack::withoutGlobalScopes()
                ->whereIn('id', $rackIds)
                ->get()
                ->mapWithKeys(function ($r) {
                    $label = trim((string) ($r->code ?? ''));
                    if ($label === '') $label = trim((string) ($r->name ?? ''));
                    if ($label === '') $label = 'Rack#' . (int) $r->id;
                    return [(int) $r->id => $label];
                })
                ->toArray();
        }

        // outgoing logs (sender)
        foreach ($transfer->items as $it) {
            $pid = (int) $it->product_id;
            $p = $it->product;
            $pLabel = $p ? ($p->product_name . ' (' . $p->product_code . ')') : ('Product ID ' . $pid);

            $cond = strtoupper(strtolower((string) ($it->condition ?? 'good')));

            $qty = (int) $it->quantity;

            $fromRackId = (int) ($it->from_rack_id ?? 0);
            $fromRackLabel = $fromRackId > 0 ? ($rackMap[$fromRackId] ?? ('Rack#' . $fromRackId)) : '-';

            $rackLogsOutgoing[] = "Ambil {$qty} {$pLabel} ({$cond}) dari Rack {$fromRackLabel}";
        }

        // incoming logs only when confirmed/issue (karena incoming rack ditentukan saat konfirmasi)
        if (in_array($status, ['confirmed', 'issue'], true)) {

            // GOOD allocations: baca dari transfer_receive_allocations
            $allocs = \Modules\Transfer\Entities\TransferReceiveAllocation::withoutGlobalScopes()
                ->where('transfer_request_id', (int) $transfer->id)
                ->orderBy('id', 'asc')
                ->get();

            $goodGroups = $allocs
                ->where('qty_good', '>', 0)
                ->groupBy(function ($a) {
                    return (int) $a->product_id . '|' . (int) $a->rack_id;
                });

            foreach ($goodGroups as $key => $rows) {
                [$pid, $rid] = explode('|', $key);
                $pid = (int) $pid; $rid = (int) $rid;

                $p = $transfer->items->firstWhere('product_id', $pid)?->product;
                $pLabel = $p ? ($p->product_name . ' (' . $p->product_code . ')') : ('Product ID ' . $pid);

                $qty = (int) $rows->sum('qty_good');
                $rackLabel = $rid > 0 ? ($rackMap[$rid] ?? ('Rack#' . $rid)) : '-';

                $rackLogsIncoming[] = "Masukkan {$qty} {$pLabel} (GOOD) ke Rack {$rackLabel}";
            }

            // DEFECT groups from product_defect_items
            $defectGroups = $defects->groupBy(function ($d) {
                return (int) $d->product_id . '|' . (int) ($d->rack_id ?? 0);
            });

            foreach ($defectGroups as $key => $rows) {
                [$pid, $rid] = explode('|', $key);
                $pid = (int) $pid; $rid = (int) $rid;

                $p = $transfer->items->firstWhere('product_id', $pid)?->product;
                $pLabel = $p ? ($p->product_name . ' (' . $p->product_code . ')') : ('Product ID ' . $pid);

                $qty = (int) $rows->sum('quantity');
                $rackLabel = $rid > 0 ? ($rackMap[$rid] ?? ('Rack#' . $rid)) : '-';

                $rackLogsIncoming[] = "Masukkan {$qty} {$pLabel} (DEFECT) ke Rack {$rackLabel}";
            }

            // DAMAGED groups from product_damaged_items where damage_type=damaged
            $damagedOnly = $issues->where('damage_type', 'damaged')->values();
            $damagedGroups = $damagedOnly->groupBy(function ($d) {
                return (int) $d->product_id . '|' . (int) ($d->rack_id ?? 0);
            });

            foreach ($damagedGroups as $key => $rows) {
                [$pid, $rid] = explode('|', $key);
                $pid = (int) $pid; $rid = (int) $rid;

                $p = $transfer->items->firstWhere('product_id', $pid)?->product;
                $pLabel = $p ? ($p->product_name . ' (' . $p->product_code . ')') : ('Product ID ' . $pid);

                $qty = (int) $rows->sum('quantity');
                $rackLabel = $rid > 0 ? ($rackMap[$rid] ?? ('Rack#' . $rid)) : '-';

                $rackLogsIncoming[] = "Masukkan {$qty} {$pLabel} (DAMAGED) ke Rack {$rackLabel}";
            }
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
            'totalMissing',
            'rackLogsOutgoing',
            'rackLogsIncoming'
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

        $rawStatus = $this->normalizeStatus((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending'));
        if ($rawStatus === 'cancelled') {
            abort(422, 'Transfer is cancelled.');
        }

        $user = Auth::user();

        DB::transaction(function () use ($transfer, $user) {

            if (!$transfer->printed_at) {

                $code = $transfer->delivery_code ?: $this->generateUniqueDeliveryCode();

                $transfer->update([
                    'printed_at'    => now(),
                    'printed_by'    => $user->id,
                    'status'        => 'shipped',
                    'delivery_code' => $code,
                ]);

                $alreadyOut = Mutation::withoutGlobalScopes()
                    ->where('reference', $transfer->reference)
                    ->where('note', 'like', 'Transfer OUT%')
                    ->exists();

                if (!$alreadyOut) {

                    $transfer->loadMissing(['items', 'fromWarehouse']);
                    $fromWarehouseId = (int) $transfer->from_warehouse_id;
                    $branchId = (int) $transfer->branch_id;

                    foreach ($transfer->items as $item) {
                        $qty = (int) $item->quantity;
                        if ($qty <= 0) continue;

                        $fromRackId = (int) ($item->from_rack_id ?? 0);
                        if ($fromRackId <= 0) {
                            abort(422, "Missing FROM rack for transfer item ID {$item->id}. Please edit transfer and set rack.");
                        }

                        $this->assertRackBelongsToWarehouse($fromRackId, $fromWarehouseId);

                        $rackCode = Rack::withoutGlobalScopes()->where('id', $fromRackId)->value('code');
                        $rackLabel = $rackCode ? "Rack {$rackCode}" : "Rack#{$fromRackId}";

                        $note = "Transfer OUT #{$transfer->reference} | From WH {$fromWarehouseId} | From {$rackLabel}";

                        $this->mutationController->applyInOut(
                            $branchId,
                            $fromWarehouseId,
                            (int) $item->product_id,
                            'Out',
                            $qty,
                            (string) $transfer->reference,
                            $note,
                            (string) $transfer->getRawOriginal('date'),
                            $fromRackId // ✅ NEW
                        );

                        // ✅ StockRack OUT
                        $cond = strtolower((string) ($item->condition ?? 'good'));
                        $dAll = -$qty;
                        $dGood = 0; $dDef = 0; $dDmg = 0;

                        if ($cond === 'good')   $dGood = -$qty;
                        if ($cond === 'defect') $dDef  = -$qty;
                        if ($cond === 'damaged')$dDmg  = -$qty;

                        $this->adjustStockRack(
                            $branchId,
                            $fromWarehouseId,
                            $fromRackId,
                            (int) $item->product_id,
                            $dAll,
                            $dGood,
                            $dDef,
                            $dDmg
                        );

                        // ✅ tandai kualitas keluar (punya kamu, keep)
                        if ($qty > 0 && in_array($cond, ['defect', 'damaged'], true)) {

                            if ($cond === 'defect') {
                                $ids = DB::table('product_defect_items')
                                    ->where('branch_id', $branchId)
                                    ->where('warehouse_id', $fromWarehouseId)
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
                                    ->where('branch_id', $branchId)
                                    ->where('warehouse_id', $fromWarehouseId)
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

            PrintLog::create([
                'user_id'             => $user->id,
                'transfer_request_id' => $transfer->id,
                'printed_at'          => now(),
                'ip_address'          => request()->ip(),
            ]);
        });

        $copyNumber = (int) PrintLog::withoutGlobalScopes()
            ->where('transfer_request_id', (int) $transfer->id)
            ->count();

        $transfer->refresh();

        return response()->json([
            'ok'          => true,
            'status'      => $this->normalizeStatus((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending')),
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

        $rawStatus = $this->normalizeStatus((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending'));

        if ($rawStatus === 'cancelled') {
            return redirect()
                ->route('transfers.show', $transfer->id)
                ->with('error', "This transfer has been cancelled, so it can't be confirmed.");
        }

        if ($rawStatus !== 'shipped') {
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

        // ✅ Racks for receiver warehouses (untuk TO rack dropdown)
        $racksByWarehouse = Rack::query()
            ->whereIn('warehouse_id', $warehouses->pluck('id')->map(fn($x)=>(int)$x)->toArray())
            ->orderByRaw("CASE WHEN code IS NULL OR code = '' THEN 1 ELSE 0 END ASC")
            ->orderBy('code')
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->groupBy('warehouse_id');

        return view('transfer::confirm', compact('transfer', 'warehouses', 'racksByWarehouse'));
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

        $rawStatus = $this->normalizeStatus((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending'));

        if ($rawStatus === 'cancelled') {
            return redirect()
                ->route('transfers.show', $transfer->id)
                ->with('error', "This transfer has been cancelled, so it can't be confirmed.");
        }

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

        $request->validate([
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'delivery_code'   => 'required|string|size:6',
            'confirm_issue'   => 'nullable|in:0,1',

            'items'                 => 'required|array|min:1',

            'items.*.item_id'       => 'required|integer',
            'items.*.product_id'    => 'required|integer',
            'items.*.qty_sent'      => 'required|integer|min:0',
            'items.*.condition'     => 'required|string|in:good,defect,damaged',

            'items.*.qty_received'  => 'required|integer|min:0',
            'items.*.qty_defect'    => 'required|integer|min:0',
            'items.*.qty_damaged'   => 'required|integer|min:0',

            // GOOD allocations (optional, tapi akan dicek manual kalau qty_received > 0)
            'items.*.good_allocations'               => 'nullable|array',
            'items.*.good_allocations.*.to_rack_id'  => 'nullable|integer|min:1',
            'items.*.good_allocations.*.qty'         => 'nullable|integer|min:0',

            // DEFECT per unit
            'items.*.defects'                         => 'nullable|array',
            'items.*.defects.*.to_rack_id'            => 'nullable|integer|min:1',
            'items.*.defects.*.defect_type'           => 'nullable|string|max:255',
            'items.*.defects.*.defect_description'    => 'nullable|string|max:2000',
            'items.*.defects.*.photo'                 => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            // DAMAGED per unit
            'items.*.damaged_items'                   => 'nullable|array',
            'items.*.damaged_items.*.to_rack_id'      => 'nullable|integer|min:1',
            'items.*.damaged_items.*.damaged_reason'  => 'nullable|string|max:2000',
            'items.*.damaged_items.*.photo'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',

            // MISSING (tetap sama)
            'items.*.missing_items'                   => 'nullable|array',
            'items.*.missing_items.*.missing_reason'  => 'nullable|string|max:2000',
            'items.*.missing_items.*.photo'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if (strtoupper($request->delivery_code) !== strtoupper((string) $transfer->delivery_code)) {
            abort(422, 'Invalid delivery code.');
        }

        $toWarehouseId = (int) $request->to_warehouse_id;
        $toWh = Warehouse::withoutGlobalScopes()->findOrFail($toWarehouseId);
        if ((int) $toWh->branch_id !== (int) $transfer->to_branch_id) {
            abort(422, 'Destination warehouse must belong to receiver branch.');
        }

        $itemsById = $transfer->items->keyBy('id');

        // Kumpulkan semua rack_id yang dipakai (untuk divalidasi belong to warehouse)
        $rackIdsToValidate = [];

        foreach ($request->items as $rowIndex => $row) {
            $good = (int) ($row['qty_received'] ?? 0);
            $defect = (int) ($row['qty_defect'] ?? 0);
            $damaged = (int) ($row['qty_damaged'] ?? 0);

            $goodAllocs = $row['good_allocations'] ?? [];
            if ($good > 0) {
                if (!is_array($goodAllocs) || count($goodAllocs) < 1) {
                    abort(422, "Row #".($rowIndex+1).": GOOD > 0 requires rack allocations.");
                }

                $sumAlloc = 0;
                foreach ($goodAllocs as $a) {
                    $q = (int) ($a['qty'] ?? 0);
                    $rid = (int) ($a['to_rack_id'] ?? 0);

                    if ($q < 0) abort(422, "Row #".($rowIndex+1).": allocation qty cannot be negative.");

                    if ($q > 0 && $rid <= 0) {
                        abort(422, "Row #".($rowIndex+1).": allocation rack is required when allocation qty > 0.");
                    }

                    $sumAlloc += $q;
                    if ($rid > 0) $rackIdsToValidate[] = $rid;
                }

                if ($sumAlloc !== $good) {
                    abort(422, "Row #".($rowIndex+1).": Total allocation ({$sumAlloc}) must equal GOOD ({$good}).");
                }
            }

            if ($defect > 0) {
                $defects = $row['defects'] ?? [];
                if (!is_array($defects) || count($defects) !== $defect) {
                    abort(422, "Row #".($rowIndex+1).": Defect count mismatch with per-unit details.");
                }
                foreach ($defects as $d) {
                    $rid = (int) ($d['to_rack_id'] ?? 0);
                    $type = trim((string) ($d['defect_type'] ?? ''));
                    if ($rid <= 0) abort(422, "Row #".($rowIndex+1).": To Rack is required for each defect unit.");
                    if ($type === '') abort(422, "Row #".($rowIndex+1).": Defect Type is required for each defect unit.");
                    $rackIdsToValidate[] = $rid;
                }
            }

            if ($damaged > 0) {
                $damages = $row['damaged_items'] ?? [];
                if (!is_array($damages) || count($damages) !== $damaged) {
                    abort(422, "Row #".($rowIndex+1).": Damaged count mismatch with per-unit details.");
                }
                foreach ($damages as $d) {
                    $rid = (int) ($d['to_rack_id'] ?? 0);
                    $reason = trim((string) ($d['damaged_reason'] ?? ''));
                    if ($rid <= 0) abort(422, "Row #".($rowIndex+1).": To Rack is required for each damaged unit.");
                    if ($reason === '') abort(422, "Row #".($rowIndex+1).": Damaged Reason is required for each damaged unit.");
                    $rackIdsToValidate[] = $rid;
                }
            }
        }

        $rackIdsToValidate = array_values(array_unique(array_filter($rackIdsToValidate)));

        foreach ($rackIdsToValidate as $rid) {
            $this->assertRackBelongsToWarehouse((int) $rid, $toWarehouseId);
        }

        DB::transaction(function () use ($request, $transfer, $itemsById, $toWarehouseId) {

            $alreadyIn = Mutation::withoutGlobalScopes()
                ->where('reference', $transfer->reference)
                ->where('note', 'like', 'Transfer IN%')
                ->exists();

            if ($alreadyIn) {
                abort(422, 'Transfer already confirmed (stock movement exists).');
            }

            $hasIssue = false;

            foreach ($request->items as $rowIndex => $row) {

                $itemId    = (int) $row['item_id'];
                $productId = (int) $row['product_id'];
                $sentForm  = (int) $row['qty_sent'];
                $condForm  = strtolower((string) $row['condition']);

                $qtyReceived = (int) $row['qty_received'];
                $qtyDefect   = (int) $row['qty_defect'];
                $qtyDamaged  = (int) $row['qty_damaged'];

                $totalIn = $qtyReceived + $qtyDefect + $qtyDamaged;

                if ($totalIn > $sentForm) {
                    abort(422, "Invalid input: Good + Defect + Damaged cannot be greater than Sent.");
                }

                if ($condForm === 'defect' && $qtyReceived > 0) {
                    abort(422, "Invalid input: Item sent as DEFECT cannot be received as GOOD.");
                }

                if ($condForm === 'damaged' && ($qtyReceived > 0 || $qtyDefect > 0)) {
                    abort(422, "Invalid input: Item sent as DAMAGED cannot be received as GOOD/DEFECT.");
                }

                $item = $itemsById->get($itemId);
                if (!$item) {
                    abort(422, "Invalid items payload: item_id {$itemId} not found in this transfer. Please reload.");
                }

                if ((int) $item->product_id !== $productId) {
                    abort(422, "Invalid payload: product mismatch for item_id {$itemId}. Please reload.");
                }

                if ((int) $item->quantity !== $sentForm) {
                    abort(422, "Invalid payload: qty_sent mismatch for item_id {$itemId}. Please reload.");
                }

                $missingQty = $sentForm - $totalIn;
                if ($missingQty > 0) $hasIssue = true;

                $toBranchId = (int) $transfer->to_branch_id;
                $noteBase = "Transfer IN #{$transfer->reference} | To WH {$toWarehouseId}";

                // =========================
                // 1) GOOD allocations (split racks)
                // =========================
                $goodAllocs = $row['good_allocations'] ?? [];
                if ($qtyReceived > 0) {

                    foreach ($goodAllocs as $a) {
                        $allocQty = (int) ($a['qty'] ?? 0);
                        $toRackId = (int) ($a['to_rack_id'] ?? 0);

                        if ($allocQty <= 0) continue; // skip kosong

                        $rackCode  = Rack::withoutGlobalScopes()->where('id', $toRackId)->value('code');
                        $rackLabel = $rackCode ? "Rack {$rackCode}" : "Rack#{$toRackId}";

                        // log allocation (good)
                        \Modules\Transfer\Entities\TransferReceiveAllocation::create([
                            'transfer_request_id'      => (int) $transfer->id,
                            'transfer_request_item_id' => (int) $itemId,
                            'branch_id'                => $toBranchId,
                            'warehouse_id'             => $toWarehouseId,
                            'product_id'               => $productId,
                            'rack_id'                  => $toRackId,
                            'qty_good'                 => $allocQty,
                            'qty_defect'               => 0,
                            'qty_damaged'              => 0,
                            'created_by'               => auth()->id(),
                        ]);

                        // mutation in per rack
                        $this->mutationController->applyInOut(
                            $toBranchId,
                            $toWarehouseId,
                            $productId,
                            'In',
                            $allocQty,
                            $transfer->reference,
                            "{$noteBase} | To {$rackLabel} | GOOD",
                            now()->toDateString(),
                            $toRackId // ✅ NEW
                        );

                        // stock_racks per rack
                        $this->adjustStockRack(
                            $toBranchId,
                            $toWarehouseId,
                            $toRackId,
                            $productId,
                            +$allocQty,
                            +$allocQty,
                            0,
                            0
                        );
                    }
                }

                // =========================
                // 2) DEFECT per unit (rack per unit)
                // =========================
                if ($qtyDefect > 0) {
                    $defectsPayload = $row['defects'] ?? [];

                    foreach ($defectsPayload as $i => $d) {
                        $toRackId = (int) ($d['to_rack_id'] ?? 0);
                        $photoPath = null;

                        if ($request->hasFile("items.$rowIndex.defects.$i.photo")) {
                            $file = $request->file("items.$rowIndex.defects.$i.photo");
                            if ($file && $file->isValid()) {
                                $photoPath = $file->store('defects', 'public');
                            }
                        }

                        $rackCode  = Rack::withoutGlobalScopes()->where('id', $toRackId)->value('code');
                        $rackLabel = $rackCode ? "Rack {$rackCode}" : "Rack#{$toRackId}";

                        // allocation record (defect)
                        \Modules\Transfer\Entities\TransferReceiveAllocation::create([
                            'transfer_request_id'      => (int) $transfer->id,
                            'transfer_request_item_id' => (int) $itemId,
                            'branch_id'                => $toBranchId,
                            'warehouse_id'             => $toWarehouseId,
                            'product_id'               => $productId,
                            'rack_id'                  => $toRackId,
                            'qty_good'                 => 0,
                            'qty_defect'               => 1,
                            'qty_damaged'              => 0,
                            'created_by'               => auth()->id(),
                        ]);

                        // mutation in 1 unit
                        $this->mutationController->applyInOut(
                            $toBranchId,
                            $toWarehouseId,
                            $productId,
                            'In',
                            1,
                            $transfer->reference,
                            "{$noteBase} | To {$rackLabel} | DEFECT",
                            now()->toDateString(),
                            $toRackId // ✅ NEW
                        );

                        $this->adjustStockRack(
                            $toBranchId,
                            $toWarehouseId,
                            $toRackId,
                            $productId,
                            +1,
                            0,
                            +1,
                            0
                        );

                        ProductDefectItem::create([
                            'branch_id'      => $toBranchId,
                            'warehouse_id'   => $toWarehouseId,
                            'rack_id'        => $toRackId, // ✅ NEW
                            'product_id'     => $productId,
                            'reference_id'   => (int) $transfer->id,
                            'reference_type' => TransferRequest::class,
                            'quantity'       => 1,
                            'defect_type'    => $d['defect_type'] ?? null,
                            'description'    => $d['defect_description'] ?? null,
                            'photo_path'     => $photoPath,
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }

                // =========================
                // 3) DAMAGED per unit (rack per unit)
                // =========================
                if ($qtyDamaged > 0) {
                    $damagedPayload = $row['damaged_items'] ?? [];

                    foreach ($damagedPayload as $i => $d) {
                        $toRackId = (int) ($d['to_rack_id'] ?? 0);
                        $photoPath = null;

                        if ($request->hasFile("items.$rowIndex.damaged_items.$i.photo")) {
                            $file = $request->file("items.$rowIndex.damaged_items.$i.photo");
                            if ($file && $file->isValid()) {
                                $photoPath = $file->store('damages', 'public');
                            }
                        }

                        $rackCode  = Rack::withoutGlobalScopes()->where('id', $toRackId)->value('code');
                        $rackLabel = $rackCode ? "Rack {$rackCode}" : "Rack#{$toRackId}";

                        \Modules\Transfer\Entities\TransferReceiveAllocation::create([
                            'transfer_request_id'      => (int) $transfer->id,
                            'transfer_request_item_id' => (int) $itemId,
                            'branch_id'                => $toBranchId,
                            'warehouse_id'             => $toWarehouseId,
                            'product_id'               => $productId,
                            'rack_id'                  => $toRackId,
                            'qty_good'                 => 0,
                            'qty_defect'               => 0,
                            'qty_damaged'              => 1,
                            'created_by'               => auth()->id(),
                        ]);

                        $this->mutationController->applyInOut(
                            $toBranchId,
                            $toWarehouseId,
                            $productId,
                            'In',
                            1,
                            $transfer->reference,
                            "{$noteBase} | To {$rackLabel} | DAMAGED",
                            now()->toDateString(),
                            $toRackId // ✅ NEW
                        );

                        $this->adjustStockRack(
                            $toBranchId,
                            $toWarehouseId,
                            $toRackId,
                            $productId,
                            +1,
                            0,
                            0,
                            +1
                        );

                        ProductDamagedItem::create([
                            'branch_id'         => $toBranchId,
                            'warehouse_id'      => $toWarehouseId,
                            'rack_id'           => $toRackId, // ✅ NEW
                            'product_id'        => $productId,
                            'reference_id'      => (int) $transfer->id,
                            'reference_type'    => TransferRequest::class,
                            'quantity'          => 1,
                            'damage_type'       => 'damaged',
                            'cause'             => 'transfer',
                            'resolution_status' => 'pending',
                            'reason'            => $d['damaged_reason'] ?? null,
                            'photo_path'        => $photoPath,
                            'created_by'        => auth()->id(),
                            'mutation_in_id'    => null,
                            'mutation_out_id'   => null,
                        ]);
                    }
                }

                // =========================
                // 4) MISSING per unit (no rack)
                // =========================
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
                            'branch_id'         => $toBranchId,
                            'warehouse_id'      => $toWarehouseId,
                            'rack_id'           => null,
                            'product_id'        => $productId,
                            'reference_id'      => (int) $transfer->id,
                            'reference_type'    => TransferRequest::class,
                            'quantity'          => 1,
                            'damage_type'       => 'missing',
                            'cause'             => 'transfer',
                            'resolution_status' => 'pending',
                            'reason'            => $detail['missing_reason'] ?? 'Missing on confirmation',
                            'photo_path'        => $photoPath,
                            'created_by'        => auth()->id(),
                            'mutation_in_id'    => null,
                            'mutation_out_id'   => null,
                        ]);
                    }
                }
            }

            if ($hasIssue && (string) $request->get('confirm_issue', '0') !== '1') {
                abort(422, "There is a remaining/missing quantity. Please confirm 'Complete with issue' to proceed.");
            }

            $transfer->update([
                'status'          => $hasIssue ? 'issue' : 'confirmed',
                'confirmed_by'    => auth()->id(),
                'confirmed_at'    => now(),
                'to_warehouse_id' => (int) $request->to_warehouse_id,
            ]);
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
