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
            abort(422, "Please choose a specific branch (not 'All Branch') to create a transfer.");
        }

        return (int) $active;
    }

    public function create()
    {
        abort_if(Gate::denies('access_transfers'), 403);

        $warehouses = Warehouse::query()
            ->when(session('active_branch') !== 'all', function ($q) {
                $q->where('branch_id', session('active_branch'));
            })
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
            'reference' => 'nullable|string|max:50',
            'date'              => 'required|date',
            'from_warehouse_id'  => 'required|exists:warehouses,id',
            'to_branch_id'       => 'required|exists:branches,id|different:' . $branchId,
            'note'              => 'nullable|string|max:1000',

            'product_ids'       => 'required|array|min:1',
            'product_ids.*'     => 'required|integer|exists:products,id',
            'quantities'        => 'required|array|min:1',
            'quantities.*'      => 'required|integer|min:1',
        ]);

        if (count($request->product_ids) !== count($request->quantities)) {
            abort(422, 'Product and quantity counts do not match.');
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

                abort(422, "Stock not enough for: {$name}. Requested: {$qty}, Available in selected warehouse: {$available}.");
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
        $ids = $transfer->items->pluck('product_id')->unique()->values();

        // dd([
        //     'transfer_id' => $transfer->id,
        //     'item_product_ids' => $ids,
        //     'products_found' => \Modules\Product\Entities\Product::withoutGlobalScopes()
        //         ->whereIn('id', $ids)
        //         ->pluck('id'),
        //     'missing_ids' => $ids->diff(
        //         \Modules\Product\Entities\Product::withoutGlobalScopes()
        //             ->whereIn('id', $ids)
        //             ->pluck('id')
        //     )->values(),
        // ]);


        // Ambil defect & damaged untuk transfer ini (reference_id + reference_type)
        $defects = ProductDefectItem::query()
            ->where('reference_type', TransferRequest::class)
            ->where('reference_id', (int) $transfer->id)
            ->orderBy('id', 'asc')
            ->get();

        $damaged = ProductDamagedItem::query()
            ->where('reference_type', TransferRequest::class)
            ->where('reference_id', (int) $transfer->id)
            ->orderBy('id', 'asc')
            ->get();

        // Grouping per product_id
        $defectQtyByProduct = $defects->groupBy('product_id')->map(function ($rows) {
            return (int) $rows->sum('quantity');
        })->toArray();

        $damagedQtyByProduct = $damaged->groupBy('product_id')->map(function ($rows) {
            return (int) $rows->sum('quantity');
        })->toArray();

        // Summary per item: sent, received_good, defect, damaged
        $itemSummaries = [];
        $totalDefect = 0;
        $totalDamaged = 0;

        foreach ($transfer->items as $item) {
            $pid = (int) $item->product_id;
            $sent = (int) $item->quantity;

            $defectQty = (int) ($defectQtyByProduct[$pid] ?? 0);
            $damagedQty = (int) ($damagedQtyByProduct[$pid] ?? 0);

            $receivedGood = $sent - $defectQty - $damagedQty;
            if ($receivedGood < 0) {
                // kalau ini kejadian, berarti data input mismatch atau ada data manual di DB.
                // kita clamp biar UI tidak aneh, tapi tetap kelihatan mismatchnya.
                $receivedGood = 0;
            }

            $totalDefect += $defectQty;
            $totalDamaged += $damagedQty;

            $itemSummaries[$pid] = [
                'sent'          => $sent,
                'received_good' => $receivedGood,
                'defect'        => $defectQty,
                'damaged'       => $damagedQty,
            ];
        }

        return view('transfer::show', compact(
            'transfer',
            'defects',
            'damaged',
            'itemSummaries',
            'totalDefect',
            'totalDamaged'
        ));
    }


    /**
     * PRINT SURAT JALAN:
     * - print pertama: generate delivery_code + set shipped + mutation OUT
     * - reprint: hanya admin/supervisor (delivery_code tetap sama)
     * - semua print dicatat di PrintLog
     */
    public function printPdf($id)
    {
        abort_if(Gate::denies('print_transfers'), 403);

        $transfer = TransferRequest::with(['items.product', 'fromWarehouse', 'toBranch'])->findOrFail($id);
        $setting  = Setting::first();
        $user     = Auth::user();

        $transfer->loadMissing('items');

        // penting: tentukan reprint SEBELUM update printed_at
        $isReprint = (bool) $transfer->printed_at;

        if ($isReprint) {
            if (!$user->hasAnyRole(['Super Admin', 'Administrator', 'Supervisor'])) {
                abort(403, 'Only admin/supervisor can reprint.');
            }
        }

        DB::transaction(function () use ($transfer, $user) {

            // FIRST PRINT
            if (!$transfer->printed_at) {

                // generate code kalau belum ada
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

        // reload relasi supaya code kebaca di view
        $transfer->refresh();
        $transfer->loadMissing(['items.product', 'fromWarehouse', 'toBranch']);

        // PASS $isReprint ke view untuk watermark COPY
        $pdf = Pdf::loadView('transfer::print', [
                'transfer'  => $transfer,
                'setting'   => $setting,
                'isReprint' => $isReprint,
            ])
            ->setPaper('A4', 'portrait');

        return $pdf->download("Surat_Jalan_{$transfer->reference}.pdf");
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
         * Confirm pakai delivery_code (tanpa upload file lagi)
         * NEW: penerima input qty_received/qty_defect/qty_damaged per item.
         * - Defect tetap masuk stok, tapi dicatat ke product_defect_items.
         * - Damaged dibuat mutation IN lalu OUT, lalu dicatat ke product_damaged_items.
     */
    public function storeConfirmation(Request $request, int $id)
    {
        abort_if(Gate::denies('confirm_transfers'), 403);

        $transfer = TransferRequest::withoutGlobalScopes()
            ->with(['items.product', 'fromWarehouse', 'toBranch'])
            ->findOrFail($id);

        $active = session('active_branch');
        if ($active === 'all') abort(422, "Please choose a specific branch.");
        if ((int)$transfer->to_branch_id !== (int)$active) abort(403, 'Unauthorized.');
        if ($transfer->status !== 'shipped') abort(422, 'Only shipped transfer can be confirmed.');

        $request->validate([
            'to_warehouse_id' => 'required|exists:warehouses,id',
            'delivery_code'   => 'required|string|size:6',
            'items'           => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer',
            'items.*.qty_sent'     => 'required|integer|min:0',
            'items.*.qty_received' => 'required|integer|min:0',
            'items.*.qty_defect'   => 'required|integer|min:0',
            'items.*.qty_damaged'  => 'required|integer|min:0',
        ]);

        if (strtoupper($request->delivery_code) !== strtoupper($transfer->delivery_code)) {
            abort(422, 'Invalid delivery code.');
        }

        DB::transaction(function () use ($request, $transfer) {

            // Header confirm
            $transfer->update([
                'to_warehouse_id' => (int)$request->to_warehouse_id,
                'status'          => 'confirmed',
                'confirmed_by'    => auth()->id(),
                'confirmed_at'    => now(),
            ]);

            // Anti double confirm
            $alreadyIn = Mutation::withoutGlobalScopes()
                ->where('reference', $transfer->reference)
                ->where('note', 'like', 'Transfer IN%')
                ->exists();

            if ($alreadyIn) return;

            foreach ($request->items as $idx => $row) {

                $productId   = (int)$row['product_id'];
                $qtyReceived = (int)$row['qty_received'];
                $qtyDefect   = (int)$row['qty_defect'];
                $qtyDamaged  = (int)$row['qty_damaged'];

                // ==========================
                // 1️⃣ TOTAL MASUK STOCK
                // ==========================
                $totalIn = $qtyReceived + $qtyDefect + $qtyDamaged;

                if ($totalIn > 0) {
                    $this->mutationController->applyInOut(
                        (int)$transfer->to_branch_id,
                        (int)$request->to_warehouse_id,
                        $productId,
                        'In',
                        $totalIn,
                        $transfer->reference,
                        "Transfer IN #{$transfer->reference}",
                        now()->toDateString()
                    );
                }

                // ==========================
                // 2️⃣ DEFECT (LABEL ONLY)
                // ==========================
                if ($qtyDefect > 0) {
                    foreach ($row['defects'] ?? [] as $k => $d) {
                        ProductDefectItem::create([
                            'branch_id'      => (int)$transfer->to_branch_id,
                            'warehouse_id'   => (int)$request->to_warehouse_id,
                            'product_id'     => $productId,
                            'reference_id'   => (int)$transfer->id,
                            'reference_type' => TransferRequest::class,
                            'quantity'       => 1,
                            'defect_type'    => $d['defect_type'] ?? null,
                            'description'    => $d['defect_description'] ?? null,
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }

                // ==========================
                // 3️⃣ DAMAGED (LABEL ONLY)
                // ==========================
                if ($qtyDamaged > 0) {
                    foreach ($row['damaged_items'] ?? [] as $k => $d) {
                        ProductDamagedItem::create([
                            'branch_id'      => (int)$transfer->to_branch_id,
                            'warehouse_id'   => (int)$request->to_warehouse_id,
                            'product_id'     => $productId,
                            'reference_id'   => (int)$transfer->id,
                            'reference_type' => TransferRequest::class,
                            'quantity'       => 1,
                            'reason'         => $d['damaged_reason'] ?? null,
                            'created_by'     => auth()->id(),
                            'mutation_in_id' => null,
                            'mutation_out_id'=> null,
                        ]);
                    }
                }
            }
        });

        toast('Transfer confirmed successfully', 'success');
        return redirect()->route('transfers.index', ['tab' => 'incoming']);
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

        // ✅ 1) RAW status sekali aja
        $status = strtolower(trim((string) ($transfer->getRawOriginal('status') ?? $transfer->status ?? 'pending')));

        if ($status === 'cancelled') abort(422, 'Transfer already cancelled.');

        if (!in_array($status, ['shipped', 'confirmed'], true)) {
            abort(422, 'Only shipped/confirmed transfer can be cancelled.');
        }

        DB::transaction(function () use ($request, $transfer, $status) {

            $alreadyCancelled = Mutation::withoutGlobalScopes()
                ->where('reference', $transfer->reference)
                ->where('note', 'like', 'Transfer CANCEL%')
                ->exists();

            if ($alreadyCancelled) abort(422, 'Cancel mutation already exists for this transfer.');

            $cancelNote = trim((string) $request->note);

            // ============ CASE 1: SHIPPED ============
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

            // ============ CASE 2: CONFIRMED ============
            if ($status === 'confirmed') {

                if (!$transfer->to_warehouse_id) abort(422, 'Invalid transfer data: destination warehouse is empty.');

                $damagedQtyByProduct = ProductDamagedItem::query()
                    ->where('reference_type', TransferRequest::class)
                    ->where('reference_id', (int) $transfer->id)
                    ->selectRaw('product_id, COALESCE(SUM(quantity),0) as qty')
                    ->groupBy('product_id')
                    ->pluck('qty', 'product_id')
                    ->toArray();

                foreach ($transfer->items as $item) {
                    $pid  = (int) $item->product_id;
                    $sent = (int) $item->quantity;
                    if ($sent <= 0) continue;

                    $damaged = (int) ($damagedQtyByProduct[$pid] ?? 0);
                    if ($damaged < 0) $damaged = 0;
                    if ($damaged > $sent) $damaged = $sent;

                    $moveBackQty = $sent - $damaged;

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

            // ✅ 3) forceFill biar pasti kesave
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


    /**
     * Generate code 6 char huruf+angka, dan pastikan unique di tabel.
     */
    private function generateUniqueDeliveryCode(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)); // 6 hex chars
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
