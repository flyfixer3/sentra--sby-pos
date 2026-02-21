<?php

namespace Modules\Mutation\Http\Controllers;

use Modules\Mutation\DataTables\MutationsDataTable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Product;
use Modules\Mutation\Entities\Mutation;
use Modules\Product\Entities\Warehouse;
use Modules\Inventory\Entities\Stock;

class Debit {
    public $tanggal;
    public $nominal;
    public $keterangan;
}

class Credit {
    public $tanggal;
    public $nominal;
    public $keterangan;
}

class MutationController extends Controller
{
    public function index(MutationsDataTable $dataTable)
    {
        abort_if(Gate::denies('access_mutations'), 403);

        $active = session('active_branch');

        $warehouses = Warehouse::query()
            ->when($active !== 'all', function ($q) use ($active) {
                $q->where('branch_id', (int) $active);
            })
            ->orderBy('warehouse_name')
            ->get();

        return $dataTable->render('mutation::index', compact('warehouses'));
    }

    public function create()
    {
        abort_if(Gate::denies('create_mutations'), 403);
        return view('mutation::create');
    }

    public function readCSV($csvFile, $array)
    {
        $file_handle = fopen($csvFile, 'r');
        while (!feof($file_handle)) {
            $line_of_text[] = fgetcsv($file_handle, 0, $array['delimiter']);
        }
        fclose($file_handle);
        return $line_of_text;
    }

    /**
     * MANUAL INPUT (dari menu Mutations)
     * Sekarang: bikin mutation log + update stocks.qty_available (source of truth)
     */
    public function store(Request $request)
    {
        abort_if(Gate::denies('create_mutations'), 403);

        $request->validate([
            'reference'     => 'required|string|max:255',
            'date'          => 'required|date',
            'note'          => 'nullable|string|max:1000',
            'product_ids'   => 'required|array',
            'product_ids.*' => 'required|integer',
            'quantities'    => 'required|array',
            'quantities.*'  => 'required|integer|min:1',
            'mutation_type' => 'required|in:Out,In,Transfer',

            'warehouse_out_id' => 'nullable|integer',
            'warehouse_in_id'  => 'nullable|integer',
        ]);

        DB::transaction(function () use ($request) {

            $active = session('active_branch');

            if ($request->mutation_type === 'Out') {

                $warehouseOut = Warehouse::findOrFail($request->warehouse_out_id);

                if ($active !== 'all') {
                    abort_unless((int) $warehouseOut->branch_id === (int) $active, 403);
                }

                foreach ($request->product_ids as $key => $productId) {
                    $productId = (int) $productId;
                    $qty = (int) ($request->quantities[$key] ?? 0);

                    Product::findOrFail($productId);

                    $this->applyInOut(
                        (int) $warehouseOut->branch_id,
                        (int) $warehouseOut->id,
                        $productId,
                        'Out',
                        $qty,
                        (string) $request->reference,
                        (string) ($request->note ?? ''),
                        (string) $request->date
                    );
                }

            } elseif ($request->mutation_type === 'In') {

                $warehouseIn = Warehouse::findOrFail($request->warehouse_in_id);

                if ($active !== 'all') {
                    abort_unless((int) $warehouseIn->branch_id === (int) $active, 403);
                }

                foreach ($request->product_ids as $key => $productId) {
                    $productId = (int) $productId;
                    $qty = (int) ($request->quantities[$key] ?? 0);

                    Product::findOrFail($productId);

                    $this->applyInOut(
                        (int) $warehouseIn->branch_id,
                        (int) $warehouseIn->id,
                        $productId,
                        'In',
                        $qty,
                        (string) $request->reference,
                        (string) ($request->note ?? ''),
                        (string) $request->date
                    );
                }

            } elseif ($request->mutation_type === 'Transfer') {

                $warehouseOut = Warehouse::findOrFail($request->warehouse_out_id);
                $warehouseIn  = Warehouse::findOrFail($request->warehouse_in_id);

                if ($active !== 'all') {
                    abort_unless((int) $warehouseOut->branch_id === (int) $active, 403);
                    abort_unless((int) $warehouseIn->branch_id === (int) $active, 403);
                }

                foreach ($request->product_ids as $key => $productId) {
                    $productId = (int) $productId;
                    $qty = (int) ($request->quantities[$key] ?? 0);

                    Product::findOrFail($productId);

                    $this->applyTransfer(
                        (int) $warehouseOut->id,
                        (int) $warehouseIn->id,
                        $productId,
                        $qty,
                        (string) $request->reference,
                        (string) ($request->note ?? ''),
                        (string) $request->date
                    );
                }
            }
        });

        toast('Mutation Created!', 'success');
        return redirect()->route('mutations.index');
    }

    public function show(Mutation $mutation)
    {
        abort_if(Gate::denies('show_mutations'), 403);
        return view('mutation::show', compact('mutation'));
    }

    public function edit(Mutation $mutation)
    {
        abort_if(Gate::denies('edit_mutations'), 403);
        return view('mutation::edit', compact('mutation'));
    }

    public function update(Request $request, Mutation $mutation)
    {
        abort_if(Gate::denies('edit_mutations'), 403);

        $request->validate([
            'reference'   => 'required|string|max:255',
            'date'        => 'required|date',
            'note'        => 'nullable|string|max:1000',
        ]);

        $mutation->update([
            'reference' => $request->reference,
            'date'      => $request->date,
            'note'      => $request->note,
        ]);

        toast('Mutation Updated!', 'info');
        return redirect()->route('mutations.index');
    }

    /**
     * Dipanggil dari AdjustmentController@store() untuk tipe stock_add.
     * - Buat mutation log (In) untuk GOOD/DEFECT/DAMAGED per rack.
     * - Sekalian update stocks + stock_racks via applyInOutInternal().
     *
     * NOTE:
     * - GOOD: ambil dari request allocations tidak ada di DB -> jadi kita log berdasarkan stock_racks delta,
     *   tapi di flow kamu saat ini GOOD allocations sudah di-increment langsung ke stock_racks tanpa mutation.
     *   Supaya konsisten & aman, method ini akan baca dari per-unit tables (defect/damaged) dan
     *   dari AdjustedProduct qty_good untuk GOOD namun perlu rack_id.
     *
     * Karena di store() kamu menyimpan GOOD allocations hanya ke stock_racks (bukan table detail),
     * kita ambil GOOD per rack dari stock_racks rows yang barusan diinsert/update via incStockRack:
     * -> kita log GOOD berdasarkan rack_id yang muncul pada defect/damaged + fallback ke 1 rack utama.
     *
     * REKOMENDASI (nanti): simpan GOOD allocations juga ke table detail biar bisa 100% akurat per rack.
     */
    public function createFromAdjustmentAdd(
    \Modules\Adjustment\Entities\Adjustment $adjustment,
    \Modules\Adjustment\Entities\AdjustedProduct $adjusted
    ): void {
        $branchId    = (int) ($adjustment->branch_id ?? 0);
        $warehouseId = (int) ($adjustment->warehouse_id ?? 0);
        $productId   = (int) ($adjusted->product_id ?? 0);

        if ($branchId <= 0 || $warehouseId <= 0 || $productId <= 0) {
            throw new \RuntimeException("Invalid adjustment data for mutation log.");
        }

        $date      = (string) ($adjustment->date ?? now()->toDateString());
        $reference = (string) ($adjustment->reference ?? ('ADJ-' . (int) $adjustment->id));

        $baseNote = trim(
            'Adjustment Add #' . (int) $adjustment->id
            . ($adjustment->note ? ' | ' . (string) $adjustment->note : '')
        );

        // =========================================================
        // 1) DEFECT per rack (DB: reference_id/reference_type, quantity, rack_id)
        // =========================================================
        $defectGroups = \Modules\Product\Entities\ProductDefectItem::query()
            ->where('reference_type', \Modules\Adjustment\Entities\Adjustment::class)
            ->where('reference_id', (int) $adjustment->id)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->selectRaw('rack_id, COALESCE(SUM(quantity),0) as qty')
            ->groupBy('rack_id')
            ->get();

        foreach ($defectGroups as $g) {
            $rackId = (int) ($g->rack_id ?? 0);
            $qty    = (int) ($g->qty ?? 0);
            if ($rackId <= 0 || $qty <= 0) continue;

            $this->applyInOut(
                $branchId,
                $warehouseId,
                $productId,
                'In',
                $qty,
                $reference,
                $baseNote . ' | DEFECT',
                $date,
                $rackId
            );
        }

        // =========================================================
        // 2) DAMAGED per rack (DB: reference_id/reference_type, quantity, rack_id)
        // =========================================================
        $damagedGroups = \Modules\Product\Entities\ProductDamagedItem::query()
            ->where('reference_type', \Modules\Adjustment\Entities\Adjustment::class)
            ->where('reference_id', (int) $adjustment->id)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->selectRaw('rack_id, COALESCE(SUM(quantity),0) as qty')
            ->groupBy('rack_id')
            ->get();

        foreach ($damagedGroups as $g) {
            $rackId = (int) ($g->rack_id ?? 0);
            $qty    = (int) ($g->qty ?? 0);
            if ($rackId <= 0 || $qty <= 0) continue;

            $this->applyInOut(
                $branchId,
                $warehouseId,
                $productId,
                'In',
                $qty,
                $reference,
                $baseNote . ' | DAMAGED',
                $date,
                $rackId
            );
        }

        // =========================================================
        // 3) GOOD (fallback rack kalau tidak ada breakdown)
        // =========================================================
        $goodQty = (int) ($adjusted->qty_good ?? 0);
        if ($goodQty > 0) {

            $fallbackRackId = (int) (\DB::table('racks')
                ->where('warehouse_id', $warehouseId)
                ->orderBy('id', 'asc')
                ->value('id') ?? 0);

            $this->applyInOut(
                $branchId,
                $warehouseId,
                $productId,
                'In',
                $goodQty,
                $reference,
                $baseNote . ' | GOOD',
                $date,
                $fallbackRackId > 0 ? $fallbackRackId : null
            );
        }
    }

    /**
     * PUBLIC: dipakai modul lain (Adjustment, Sale, Purchase, Transfer)
     * Bikin 1 mutation (In/Out) lalu update Stock table.
     */
    public function applyInOut(
        int $branchId,
        int $warehouseId,
        int $productId,
        string $mutationType,
        int $qty,
        string $reference,
        string $note,
        string $date,
        ?int $rackId = null // ✅ NEW
    ): void
    {
        $this->applyInOutInternal(
            $branchId,
            $warehouseId,
            $productId,
            $mutationType,
            $qty,
            $reference,
            $note,
            $date,
            $rackId
        );
    }

    /**
     * NEW: sama seperti applyInOut(), tapi mengembalikan ID mutation yang dibuat.
     * Ini dibutuhkan untuk kasus "damaged" agar bisa disimpan ke product_damaged_items.mutation_in_id/mutation_out_id.
     */
    public function applyInOutAndGetMutationId(
        int $branchId,
        int $warehouseId,
        int $productId,
        string $mutationType,
        int $qty,
        string $reference,
        string $note,
        string $date,
        ?int $rackId = null // ✅ NEW
    ): int
    {
        $mutation = $this->applyInOutInternal(
            $branchId,
            $warehouseId,
            $productId,
            $mutationType,
            $qty,
            $reference,
            $note,
            $date,
            $rackId
        );

        return (int) $mutation->id;
    }

   
    /**
     * INTERNAL CORE: melakukan lock stock, hitung early/last, create mutation, update stock.
     * ✅ UPDATED: jika rack_id ada, sync juga ke stock_racks (qty_available + bucket).
     * ✅ STRICT: kalau OUT dan stock_racks tidak cukup => throw (jangan diam-diam clamp ke 0).
     */
    private function applyInOutInternal(
        int $branchId,
        int $warehouseId,
        int $productId,
        string $mutationType,
        int $qty,
        string $reference,
        string $note,
        string $date,
        ?int $rackId = null
    ): Mutation
    {
        if (!in_array($mutationType, ['In', 'Out'], true)) {
            throw new \RuntimeException("Invalid mutationType: {$mutationType}");
        }
        if ($qty <= 0) {
            throw new \RuntimeException("Qty must be > 0");
        }

        // ---------------------------------------------
        // 0) helper: resolve bucket dari note
        // ---------------------------------------------
        $resolveBucketFromNote = function (string $noteX): ?string {
            $n = strtoupper(trim($noteX));
            if (str_contains($n, '| GOOD')) return 'qty_good';
            if (str_contains($n, '| DEFECT')) return 'qty_defect';
            if (str_contains($n, '| DAMAGED')) return 'qty_damaged';
            return null;
        };

        // ---------------------------------------------
        // 1) lock + update STOCKS (source of truth)
        // ---------------------------------------------
        $stock = Stock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            $stock = Stock::create([
                'branch_id'     => $branchId,
                'warehouse_id'  => $warehouseId,
                'product_id'    => $productId,
                'qty_available' => 0,
                'qty_reserved'  => 0,
                'qty_incoming'  => 0,
                'min_stock'     => 0,
                'note'          => null,
                'created_by'    => auth()->id(),
                'updated_by'    => auth()->id(),
            ]);

            $stock = Stock::withoutGlobalScopes()
                ->where('id', $stock->id)
                ->lockForUpdate()
                ->first();
        }

        $early = (int) $stock->qty_available;

        $in  = $mutationType === 'In' ? $qty : 0;
        $out = $mutationType === 'Out' ? $qty : 0;

        $last = $early + $in - $out;

        if ($last < 0) {
            throw new \RuntimeException("Stock minus tidak diizinkan. Product {$productId}, current {$early}, out {$qty}.");
        }

        // ---------------------------------------------
        // 2) create MUTATION LOG
        // ---------------------------------------------
        $mutation = Mutation::create([
            'branch_id'     => $branchId,
            'warehouse_id'  => $warehouseId,
            'rack_id'       => $rackId, // boleh null
            'product_id'    => $productId,
            'reference'     => $reference,
            'date'          => $date,
            'mutation_type' => $mutationType,
            'note'          => $note,
            'stock_early'   => $early,
            'stock_in'      => $in,
            'stock_out'     => $out,
            'stock_last'    => $last,
        ]);

        // ---------------------------------------------
        // 3) update STOCKS
        // ---------------------------------------------
        $stock->update([
            'qty_available' => $last,
            'updated_by'    => auth()->id(),
        ]);

        // ---------------------------------------------
        // 4) ✅ NEW: sync STOCK_RACKS jika rack_id ada
        //    STRICT: Out harus cukup, kalau tidak => throw.
        // ---------------------------------------------
        if (!empty($rackId) && (int) $rackId > 0) {

            $bucketCol = $resolveBucketFromNote($note);

            $sr = DB::table('stock_racks')
                ->where('branch_id', (int) $branchId)
                ->where('warehouse_id', (int) $warehouseId)
                ->where('rack_id', (int) $rackId)
                ->where('product_id', (int) $productId)
                ->lockForUpdate()
                ->first();

            if (!$sr) {
                // kalau IN: boleh auto-create
                // kalau OUT: ini red flag karena keluar dari rack yang tidak punya row
                if ($mutationType === 'Out') {
                    throw new \RuntimeException(
                        "Stock rack row not found for OUT. " .
                        "Branch {$branchId}, WH {$warehouseId}, Rack {$rackId}, Product {$productId}. Ref {$reference}"
                    );
                }

                DB::table('stock_racks')->insert([
                    'branch_id'     => (int) $branchId,
                    'warehouse_id'  => (int) $warehouseId,
                    'rack_id'       => (int) $rackId,
                    'product_id'    => (int) $productId,
                    'qty_available' => 0,
                    'qty_good'      => 0,
                    'qty_defect'    => 0,
                    'qty_damaged'   => 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                $sr = (object) [
                    'qty_available' => 0,
                    'qty_good'      => 0,
                    'qty_defect'    => 0,
                    'qty_damaged'   => 0,
                ];
            }

            $curAvail = (int) ($sr->qty_available ?? 0);

            if ($mutationType === 'Out' && $curAvail < $qty) {
                throw new \RuntimeException(
                    "Not enough rack qty_available for OUT. " .
                    "Need {$qty}, have {$curAvail}. " .
                    "Branch {$branchId}, WH {$warehouseId}, Rack {$rackId}, Product {$productId}. Ref {$reference}"
                );
            }

            $newAvail = $mutationType === 'In' ? ($curAvail + $qty) : ($curAvail - $qty);

            $update = [
                'qty_available' => (int) $newAvail,
                'updated_at'    => now(),
            ];

            if ($bucketCol && in_array($bucketCol, ['qty_good', 'qty_defect', 'qty_damaged'], true)) {
                $curBucket = (int) ($sr->{$bucketCol} ?? 0);

                if ($mutationType === 'Out' && $curBucket < $qty) {
                    throw new \RuntimeException(
                        "Not enough rack {$bucketCol} for OUT. " .
                        "Need {$qty}, have {$curBucket}. " .
                        "Branch {$branchId}, WH {$warehouseId}, Rack {$rackId}, Product {$productId}. Ref {$reference}"
                    );
                }

                $newBucket = $mutationType === 'In' ? ($curBucket + $qty) : ($curBucket - $qty);
                $update[$bucketCol] = (int) $newBucket;
            }

            DB::table('stock_racks')
                ->where('branch_id', (int) $branchId)
                ->where('warehouse_id', (int) $warehouseId)
                ->where('rack_id', (int) $rackId)
                ->where('product_id', (int) $productId)
                ->update($update);
        }

        return $mutation;
    }

    public function applyTransfer(
        int $warehouseOutId,
        int $warehouseInId,
        int $productId,
        int $qty,
        string $reference,
        string $note,
        string $date
    ): void
    {
        if ($qty <= 0) {
            throw new \RuntimeException("Qty must be > 0");
        }

        $warehouseOut = Warehouse::findOrFail($warehouseOutId);
        $warehouseIn  = Warehouse::findOrFail($warehouseInId);

        $this->applyTransferOneSide(
            (int) $warehouseOut->branch_id,
            (int) $warehouseOut->id,
            $productId,
            'Out',
            $qty,
            $reference,
            $note,
            $date
        );

        $this->applyTransferOneSide(
            (int) $warehouseIn->branch_id,
            (int) $warehouseIn->id,
            $productId,
            'In',
            $qty,
            $reference,
            $note,
            $date
        );
    }

    private function applyTransferOneSide(
        int $branchId,
        int $warehouseId,
        int $productId,
        string $direction,
        int $qty,
        string $reference,
        string $note,
        string $date
    ): void
    {
        if (!in_array($direction, ['In', 'Out'], true)) {
            throw new \RuntimeException("Invalid transfer direction: {$direction}");
        }

        $stock = Stock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            $stock = Stock::create([
                'branch_id'     => $branchId,
                'warehouse_id'  => $warehouseId,
                'product_id'    => $productId,
                'qty_available' => 0,
                'qty_reserved'  => 0,
                'qty_incoming'  => 0,
                'min_stock'     => 0,
                'note'          => null,
                'created_by'    => auth()->id(),
                'updated_by'    => auth()->id(),
            ]);

            $stock = Stock::withoutGlobalScopes()
                ->where('id', $stock->id)
                ->lockForUpdate()
                ->first();
        }

        $early = (int) $stock->qty_available;

        $in  = $direction === 'In' ? $qty : 0;
        $out = $direction === 'Out' ? $qty : 0;

        $last = $early + $in - $out;

        if ($last < 0) {
            throw new \RuntimeException("Stock minus tidak diizinkan. Product {$productId}, current {$early}, out {$qty}.");
        }

        Mutation::create([
            'branch_id'     => $branchId,
            'warehouse_id'  => $warehouseId,
            'product_id'    => $productId,
            'reference'     => $reference,
            'date'          => $date,
            'mutation_type' => 'Transfer',
            'note'          => $note,
            'stock_early'   => $early,
            'stock_in'      => $in,
            'stock_out'     => $out,
            'stock_last'    => $last,
        ]);

        $stock->update([
            'qty_available' => $last,
            'updated_by'    => auth()->id(),
        ]);
    }

    public function rollbackByReference(string $reference, string $notePrefix = ''): void
    {
        $mutations = Mutation::withoutGlobalScopes()
            ->where('reference', $reference)
            ->when($notePrefix !== '', function ($q) use ($notePrefix) {
                $q->where('note', 'like', $notePrefix . '%');
            })
            ->orderByDesc('id')
            ->lockForUpdate()
            ->get();

        // helper: resolve bucket dari note (sama seperti applyInOutInternal)
        $resolveBucketFromNote = function (string $noteX): ?string {
            $n = strtoupper(trim($noteX));
            if (str_contains($n, '| GOOD')) return 'qty_good';
            if (str_contains($n, '| DEFECT')) return 'qty_defect';
            if (str_contains($n, '| DAMAGED')) return 'qty_damaged';
            return null;
        };

        foreach ($mutations as $m) {

            // 1) rollback STOCK (existing behavior kamu)
            $stock = Stock::withoutGlobalScopes()
                ->where('branch_id', (int) $m->branch_id)
                ->where('warehouse_id', (int) $m->warehouse_id)
                ->where('product_id', (int) $m->product_id)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = Stock::create([
                    'branch_id'     => (int) $m->branch_id,
                    'warehouse_id'  => (int) $m->warehouse_id,
                    'product_id'    => (int) $m->product_id,
                    'qty_available' => 0,
                    'qty_reserved'  => 0,
                    'qty_incoming'  => 0,
                    'min_stock'     => 0,
                    'note'          => null,
                    'created_by'    => auth()->id(),
                    'updated_by'    => auth()->id(),
                ]);

                $stock = Stock::withoutGlobalScopes()
                    ->where('id', $stock->id)
                    ->lockForUpdate()
                    ->first();
            }

            $stock->update([
                'qty_available' => (int) $m->stock_early,
                'updated_by'    => auth()->id(),
            ]);

            // 2) ✅ rollback STOCK_RACKS (NEW)
            $rackId = (int) ($m->rack_id ?? 0);
            if ($rackId > 0) {

                $qty = 0;
                // mutation table kamu punya stock_in/stock_out
                if (strtoupper((string)$m->mutation_type) === 'IN') {
                    $qty = (int) ($m->stock_in ?? 0);
                } elseif (strtoupper((string)$m->mutation_type) === 'OUT') {
                    $qty = (int) ($m->stock_out ?? 0);
                } else {
                    // Transfer di module lain: ignore rack rollback (karena transfer kamu tidak pakai rack_id)
                    $qty = 0;
                }

                if ($qty > 0) {

                    $bucketCol = $resolveBucketFromNote((string) ($m->note ?? ''));

                    $sr = DB::table('stock_racks')
                        ->where('branch_id', (int) $m->branch_id)
                        ->where('warehouse_id', (int) $m->warehouse_id)
                        ->where('rack_id', (int) $rackId)
                        ->where('product_id', (int) $m->product_id)
                        ->lockForUpdate()
                        ->first();

                    if ($sr) {
                        $curAvail = (int) ($sr->qty_available ?? 0);

                        // reverse delta:
                        // - kalau mutation IN dulu menambah, rollback harus mengurangi
                        // - kalau mutation OUT dulu mengurangi, rollback harus menambah
                        $isIn = strtoupper((string)$m->mutation_type) === 'IN';
                        $newAvail = $isIn ? ($curAvail - $qty) : ($curAvail + $qty);
                        if ($newAvail < 0) $newAvail = 0; // safety guard

                        $update = [
                            'qty_available' => (int) $newAvail,
                            'updated_at'    => now(),
                        ];

                        if ($bucketCol && in_array($bucketCol, ['qty_good', 'qty_defect', 'qty_damaged'], true)) {
                            $curBucket = (int) ($sr->{$bucketCol} ?? 0);
                            $newBucket = $isIn ? ($curBucket - $qty) : ($curBucket + $qty);
                            if ($newBucket < 0) $newBucket = 0; // safety guard
                            $update[$bucketCol] = (int) $newBucket;
                        }

                        DB::table('stock_racks')
                            ->where('branch_id', (int) $m->branch_id)
                            ->where('warehouse_id', (int) $m->warehouse_id)
                            ->where('rack_id', (int) $rackId)
                            ->where('product_id', (int) $m->product_id)
                            ->update($update);
                    }
                }
            }

            // 3) delete mutation log
            $m->delete();
        }
    }

    public function destroy(Mutation $mutation)
    {
        abort_if(Gate::denies('delete_mutations'), 403);

        $mutation->delete();

        toast('Mutation Deleted!', 'warning');
        return redirect()->route('mutations.index');
    }
}
