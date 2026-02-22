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
        ?int $rackId = null,
        ?string $bucket = null,
        string $logMode = 'single' // ✅ NEW
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
            $rackId,
            $bucket,
            $logMode // ✅ pass-through
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
        ?int $rackId = null,
        ?string $bucket = null,
        string $logMode = 'single' // ✅ NEW
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
            $rackId,
            $bucket,
            $logMode // ✅ pass-through
        );

        return (int) $mutation->id;
    }

    /**
     * INTERNAL CORE: melakukan lock stock, hitung early/last, create mutation, update stock.
     * ✅ UPDATED: support logMode = 'single' | 'summary'
     * - single  : behavior lama (selalu create row baru)
     * - summary : merge/upsert jadi 1 row per reference+product+warehouse+date+mutation_type
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
        ?int $rackId = null,
        ?string $bucket = null, // good|defect|damaged
        string $logMode = 'single' // ✅ NEW
    ): \Modules\Mutation\Entities\Mutation
    {
        if (!in_array($mutationType, ['In', 'Out'], true)) {
            throw new \RuntimeException("Invalid mutationType: {$mutationType}");
        }
        if ($qty <= 0) {
            throw new \RuntimeException("Qty must be > 0");
        }
        if (!in_array($logMode, ['single', 'summary'], true)) {
            $logMode = 'single';
        }

        // =========================================================
        // 0) Resolve bucket column (prefer explicit bucket)
        // =========================================================
        $resolveBucketFromExplicit = function (?string $b): ?string {
            $b = strtolower(trim((string) $b));
            return match ($b) {
                'good'   => 'qty_good',
                'defect' => 'qty_defect',
                'damaged'=> 'qty_damaged',
                default  => null,
            };
        };

        $resolveBucketFromNote = function (string $noteX): ?string {
            $n = strtoupper((string) $noteX);
            if (preg_match('/\bGOOD\b/', $n)) return 'qty_good';
            if (preg_match('/\bDEFECT\b/', $n)) return 'qty_defect';
            if (preg_match('/\bDAMAGED\b/', $n)) return 'qty_damaged';
            return null;
        };

        $bucketCol = $resolveBucketFromExplicit($bucket) ?? $resolveBucketFromNote($note);

        // =========================================================
        // 0.5) Resolve rackId fallback
        // =========================================================
        $resolvedRackId = (int) ($rackId ?? 0);
        if ($resolvedRackId <= 0) {
            $resolvedRackId = (int) (DB::table('racks')
                ->where('warehouse_id', $warehouseId)
                ->orderBy('id', 'asc')
                ->value('id') ?? 0);
        }

        // =========================================================
        // 1) LOCK + UPDATE STOCKS (header total)
        // =========================================================
        $stock = \Modules\Inventory\Entities\Stock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$stock) {
            $stock = \Modules\Inventory\Entities\Stock::create([
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

            $stock = \Modules\Inventory\Entities\Stock::withoutGlobalScopes()
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

        // =========================================================
        // 2) CREATE / MERGE MUTATION LOG
        // =========================================================
        $mutation = null;

        if ($logMode === 'single') {

            // behavior lama: create row baru
            $mutation = \Modules\Mutation\Entities\Mutation::create([
                'branch_id'     => $branchId,
                'warehouse_id'  => $warehouseId,
                'rack_id'       => $resolvedRackId > 0 ? $resolvedRackId : null,
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

        } else {
            /**
             * ✅ SUMMARY MODE:
             * - 1 row per reference+product+warehouse+date+mutation_type
             * - note disimpan sebagai ringkasan + breakdown bucket
             */
            $summaryPrefix = '[SUMMARY] ';

            $existing = \Modules\Mutation\Entities\Mutation::withoutGlobalScopes()
                ->where('branch_id', $branchId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('reference', $reference)
                ->where('date', $date)
                ->where('mutation_type', $mutationType)
                ->where('note', 'like', $summaryPrefix . '%')
                ->lockForUpdate()
                ->first();

            $bucketKey = null;
            if ($bucketCol === 'qty_good') $bucketKey = 'GOOD';
            if ($bucketCol === 'qty_defect') $bucketKey = 'DEFECT';
            if ($bucketCol === 'qty_damaged') $bucketKey = 'DAMAGED';

            $bumpSummaryNote = function (string $noteText, ?string $bucketName, int $deltaQty, int $rackX = 0): string {
                // format: [SUMMARY] ... | BKT:GOOD=3,DEFECT=1,DAMAGED=0 | RACKS:12(GOOD=3),15(DEFECT=1)
                $noteText = trim($noteText);

                $getMap = function (string $label, string $text): array {
                    // label: "BKT:" or "RACKS:"
                    $map = [];
                    if (!preg_match('/\|\s*' . preg_quote($label, '/') . '\s*([^|]+)\s*/i', $text, $m)) {
                        return $map;
                    }
                    $raw = trim($m[1] ?? '');
                    if ($raw === '') return $map;

                    // BKT: "GOOD=3,DEFECT=1"
                    if (strtoupper($label) === 'BKT:') {
                        foreach (explode(',', $raw) as $pair) {
                            $pair = trim($pair);
                            if ($pair === '') continue;
                            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '0');
                            $k = strtoupper(trim($k));
                            $v = (int) trim($v);
                            if ($k !== '') $map[$k] = $v;
                        }
                        return $map;
                    }

                    // RACKS: "12(GOOD=3;DEFECT=1),15(DAMAGED=2)"
                    if (strtoupper($label) === 'RACKS:') {
                        foreach (explode(',', $raw) as $chunk) {
                            $chunk = trim($chunk);
                            if ($chunk === '') continue;
                            if (!preg_match('/^(\d+)\((.+)\)$/', $chunk, $mm)) continue;
                            $rid = (int) $mm[1];
                            $inside = (string) $mm[2];
                            $insideMap = [];
                            foreach (explode(';', $inside) as $pair) {
                                $pair = trim($pair);
                                if ($pair === '') continue;
                                [$k, $v] = array_pad(explode('=', $pair, 2), 2, '0');
                                $k = strtoupper(trim($k));
                                $v = (int) trim($v);
                                if ($k !== '') $insideMap[$k] = $v;
                            }
                            if ($rid > 0) $map[$rid] = $insideMap;
                        }
                        return $map;
                    }

                    return $map;
                };

                $setSection = function (string $label, string $text, string $newValue): string {
                    if (preg_match('/\|\s*' . preg_quote($label, '/') . '\s*[^|]+/i', $text)) {
                        return preg_replace('/\|\s*' . preg_quote($label, '/') . '\s*[^|]+/i', '| ' . $label . ' ' . $newValue, $text);
                    }
                    return rtrim($text) . ' | ' . $label . ' ' . $newValue;
                };

                // bump bucket totals
                if ($bucketName) {
                    $bkt = $getMap('BKT:', $noteText);
                    $bkt['GOOD']   = (int) ($bkt['GOOD'] ?? 0);
                    $bkt['DEFECT'] = (int) ($bkt['DEFECT'] ?? 0);
                    $bkt['DAMAGED']= (int) ($bkt['DAMAGED'] ?? 0);

                    $bkt[$bucketName] = (int) ($bkt[$bucketName] ?? 0) + $deltaQty;

                    $bktStr = 'GOOD=' . $bkt['GOOD'] . ',DEFECT=' . $bkt['DEFECT'] . ',DAMAGED=' . $bkt['DAMAGED'];
                    $noteText = $setSection('BKT:', $noteText, $bktStr);

                    // bump rack breakdown (optional)
                    if ($rackX > 0) {
                        $racks = $getMap('RACKS:', $noteText); // [rackId => [bucket=>qty]]
                        if (!isset($racks[$rackX])) $racks[$rackX] = [];
                        $racks[$rackX]['GOOD']   = (int) ($racks[$rackX]['GOOD'] ?? 0);
                        $racks[$rackX]['DEFECT'] = (int) ($racks[$rackX]['DEFECT'] ?? 0);
                        $racks[$rackX]['DAMAGED']= (int) ($racks[$rackX]['DAMAGED'] ?? 0);

                        $racks[$rackX][$bucketName] = (int) ($racks[$rackX][$bucketName] ?? 0) + $deltaQty;

                        $chunks = [];
                        foreach ($racks as $rid => $map) {
                            $rid = (int) $rid;
                            if ($rid <= 0) continue;
                            $g = (int) ($map['GOOD'] ?? 0);
                            $d = (int) ($map['DEFECT'] ?? 0);
                            $m = (int) ($map['DAMAGED'] ?? 0);

                            $inside = [];
                            if ($g > 0) $inside[] = 'GOOD=' . $g;
                            if ($d > 0) $inside[] = 'DEFECT=' . $d;
                            if ($m > 0) $inside[] = 'DAMAGED=' . $m;
                            if (empty($inside)) continue;

                            $chunks[] = $rid . '(' . implode(';', $inside) . ')';
                        }

                        if (!empty($chunks)) {
                            $noteText = $setSection('RACKS:', $noteText, implode(',', $chunks));
                        }
                    }
                }

                return trim($noteText);
            };

            if (!$existing) {
                // buat baru summary
                $base = $summaryPrefix . trim($note);
                $base = $bumpSummaryNote($base, $bucketKey, $qty, $resolvedRackId);

                $existing = \Modules\Mutation\Entities\Mutation::create([
                    'branch_id'     => $branchId,
                    'warehouse_id'  => $warehouseId,
                    'rack_id'       => null, // summary: tidak mengikat 1 rack
                    'product_id'    => $productId,
                    'reference'     => $reference,
                    'date'          => $date,
                    'mutation_type' => $mutationType,
                    'note'          => $base,
                    'stock_early'   => $early,
                    'stock_in'      => $in,
                    'stock_out'     => $out,
                    'stock_last'    => $last,
                ]);
            } else {
                $newNote = $bumpSummaryNote((string) ($existing->note ?? ($summaryPrefix . trim($note))), $bucketKey, $qty, $resolvedRackId);

                $existing->update([
                    // stock_early: keep yang paling awal (jangan ditimpa)
                    'stock_in'    => (int) ($existing->stock_in ?? 0) + $in,
                    'stock_out'   => (int) ($existing->stock_out ?? 0) + $out,
                    'stock_last'  => $last, // last selalu ikut kondisi terbaru
                    'note'        => $newNote,
                ]);
            }

            $mutation = $existing;
        }

        // =========================================================
        // 3) UPDATE STOCKS (header)
        // =========================================================
        $stock->update([
            'qty_available' => $last,
            'updated_by'    => auth()->id(),
        ]);

        // =========================================================
        // 4) UPDATE STOCK_RACKS (detail) + HARD SYNC BUCKETS
        // =========================================================
        if ($resolvedRackId > 0) {

            $resolvedSrBranchExpr = 'COALESCE(sr.branch_id, w.branch_id)';

            $sr = DB::table('stock_racks as sr')
                ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
                ->where('sr.warehouse_id', (int) $warehouseId)
                ->where('sr.rack_id', (int) $resolvedRackId)
                ->where('sr.product_id', (int) $productId)
                ->whereRaw($resolvedSrBranchExpr . ' = ?', [(int) $branchId])
                ->select('sr.*')
                ->lockForUpdate()
                ->first();

            if (!$sr) {
                if ($mutationType === 'Out') {
                    throw new \RuntimeException(
                        "Stock rack row not found for OUT. Branch {$branchId}, WH {$warehouseId}, Rack {$resolvedRackId}, Product {$productId}. Ref {$reference}"
                    );
                }

                DB::table('stock_racks')->insert([
                    'branch_id'     => (int) $branchId,
                    'warehouse_id'  => (int) $warehouseId,
                    'rack_id'       => (int) $resolvedRackId,
                    'product_id'    => (int) $productId,
                    'qty_available' => 0,
                    'qty_good'      => 0,
                    'qty_defect'    => 0,
                    'qty_damaged'   => 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                $sr = (object) ['qty_available' => 0, 'qty_good' => 0, 'qty_defect' => 0, 'qty_damaged' => 0];
            }

            $curAvail = (int) ($sr->qty_available ?? 0);

            if ($mutationType === 'Out' && $curAvail < $qty) {
                throw new \RuntimeException(
                    "Not enough rack qty_available for OUT. Need {$qty}, have {$curAvail}. Branch {$branchId}, WH {$warehouseId}, Rack {$resolvedRackId}, Product {$productId}. Ref {$reference}"
                );
            }

            $newAvail = $mutationType === 'In' ? ($curAvail + $qty) : ($curAvail - $qty);

            $update = [
                'qty_available' => (int) $newAvail,
                'updated_at'    => now(),
            ];

            if ($bucketCol && in_array($bucketCol, ['qty_good', 'qty_defect', 'qty_damaged'], true)) {
                $curBucket = (int) ($sr->{$bucketCol} ?? 0);

                if (!($mutationType === 'Out' && $curBucket < $qty)) {
                    $newBucket = $mutationType === 'In' ? ($curBucket + $qty) : ($curBucket - $qty);
                    if ($newBucket < 0) $newBucket = 0;
                    $update[$bucketCol] = (int) $newBucket;
                }
            }

            DB::table('stock_racks')
                ->where('warehouse_id', (int) $warehouseId)
                ->where('rack_id', (int) $resolvedRackId)
                ->where('product_id', (int) $productId)
                ->where(function ($q) use ($branchId) {
                    $q->where('branch_id', (int) $branchId)->orWhereNull('branch_id');
                })
                ->update($update);

            $this->syncStockRackQualityFromItems($branchId, $warehouseId, $productId, $resolvedRackId);
        }

        // =========================================================
        // 5) GUARD total rack qty == stocks header
        // =========================================================
        $this->assertStockHeaderEqualsRackSum($branchId, $warehouseId, $productId);

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

    private function assertStockHeaderEqualsRackSum(int $branchId, int $warehouseId, int $productId): void
    {
        if ($branchId <= 0 || $warehouseId <= 0 || $productId <= 0) {
            return;
        }

        $stockQty = (int) \Modules\Inventory\Entities\Stock::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->value('qty_available');

        // support legacy sr.branch_id NULL
        $resolvedSrBranchExpr = 'COALESCE(sr.branch_id, w.branch_id)';

        $rackSum = (int) DB::table('stock_racks as sr')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->where('sr.warehouse_id', $warehouseId)
            ->where('sr.product_id', $productId)
            ->whereRaw($resolvedSrBranchExpr . ' = ?', [$branchId])
            ->sum('sr.qty_available');

        if ($stockQty !== $rackSum) {
            throw new \RuntimeException(
                "Stock mismatch detected! stocks.qty_available ({$stockQty}) != SUM(stock_racks.qty_available) ({$rackSum}). " .
                "Branch {$branchId}, WH {$warehouseId}, Product {$productId}."
            );
        }
    }

    private function syncStockRackQualityFromItems(
        int $branchId,
        int $warehouseId,
        int $productId,
        int $rackId
    ): void
    {
        if ($branchId <= 0 || $warehouseId <= 0 || $productId <= 0 || $rackId <= 0) {
            return;
        }

        // support legacy sr.branch_id NULL
        $resolvedSrBranchExpr = 'COALESCE(sr.branch_id, w.branch_id)';

        // lock sr row
        $sr = DB::table('stock_racks as sr')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->where('sr.warehouse_id', (int) $warehouseId)
            ->where('sr.rack_id', (int) $rackId)
            ->where('sr.product_id', (int) $productId)
            ->whereRaw($resolvedSrBranchExpr . ' = ?', [(int) $branchId])
            ->select('sr.*')
            ->lockForUpdate()
            ->first();

        if (!$sr) {
            // create row if missing
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

            $sr = (object) ['qty_available' => 0];
        }

        $qtyAvail = (int) ($sr->qty_available ?? 0);

        // DEFECT: hanya yang masih available (moved_out_at IS NULL)
        $defect = (int) DB::table('product_defect_items')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('rack_id', $rackId)
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->sum('quantity');

        // DAMAGED: hanya yang pending + belum moved out
        $damaged = (int) DB::table('product_damaged_items')
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('rack_id', $rackId)
            ->where('product_id', $productId)
            ->whereNull('moved_out_at')
            ->where(function ($q) {
                $q->whereNull('resolution_status')
                ->orWhere('resolution_status', 'pending');
            })
            ->sum('quantity');

        $good = $qtyAvail - $defect - $damaged;
        if ($good < 0) $good = 0;

        DB::table('stock_racks')
            ->where('warehouse_id', (int) $warehouseId)
            ->where('rack_id', (int) $rackId)
            ->where('product_id', (int) $productId)
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', (int) $branchId)->orWhereNull('branch_id');
            })
            ->update([
                'qty_defect'  => $defect,
                'qty_damaged' => $damaged,
                'qty_good'    => $good,
                'updated_at'  => now(),
            ]);
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
        if ($qty <= 0) {
            throw new \RuntimeException("Qty must be > 0");
        }

        // fallback rack default (biar racks & header tetap sync)
        $resolvedRackId = (int) (DB::table('racks')
            ->where('warehouse_id', $warehouseId)
            ->orderBy('id', 'asc')
            ->value('id') ?? 0);

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
            'rack_id'       => $resolvedRackId > 0 ? $resolvedRackId : null, // ✅ now stored
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

        // ✅ update stock_racks juga (fallback rack)
        if ($resolvedRackId > 0) {
            $resolvedSrBranchExpr = 'COALESCE(sr.branch_id, w.branch_id)';

            $sr = DB::table('stock_racks as sr')
                ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
                ->where('sr.warehouse_id', (int) $warehouseId)
                ->where('sr.rack_id', (int) $resolvedRackId)
                ->where('sr.product_id', (int) $productId)
                ->whereRaw($resolvedSrBranchExpr . ' = ?', [(int) $branchId])
                ->select('sr.*')
                ->lockForUpdate()
                ->first();

            if (!$sr) {
                if ($direction === 'Out') {
                    throw new \RuntimeException(
                        "Stock rack row not found for Transfer OUT. Branch {$branchId}, WH {$warehouseId}, Rack {$resolvedRackId}, Product {$productId}. Ref {$reference}"
                    );
                }

                DB::table('stock_racks')->insert([
                    'branch_id'     => (int) $branchId,
                    'warehouse_id'  => (int) $warehouseId,
                    'rack_id'       => (int) $resolvedRackId,
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
                ];
            }

            $curAvail = (int) ($sr->qty_available ?? 0);

            if ($direction === 'Out' && $curAvail < $qty) {
                throw new \RuntimeException(
                    "Not enough rack qty_available for Transfer OUT. Need {$qty}, have {$curAvail}. Branch {$branchId}, WH {$warehouseId}, Rack {$resolvedRackId}, Product {$productId}. Ref {$reference}"
                );
            }

            $newAvail = $direction === 'In' ? ($curAvail + $qty) : ($curAvail - $qty);

            DB::table('stock_racks')
                ->where('warehouse_id', (int) $warehouseId)
                ->where('rack_id', (int) $resolvedRackId)
                ->where('product_id', (int) $productId)
                ->where(function ($q) use ($branchId) {
                    $q->where('branch_id', (int) $branchId)->orWhereNull('branch_id');
                })
                ->update([
                    'qty_available' => (int) $newAvail,
                    'updated_at'    => now(),
                ]);

            // ✅ sync quality buckets & derive good
            $this->syncStockRackQualityFromItems($branchId, $warehouseId, $productId, $resolvedRackId);
        }

        // ✅ guard totals
        $this->assertStockHeaderEqualsRackSum($branchId, $warehouseId, $productId);
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

        // helper: resolve bucket dari note (non-summary single rows)
        $resolveBucketFromNote = function (string $noteX): ?string {
            $n = strtoupper(trim($noteX));
            if (preg_match('/\bGOOD\b/', $n)) return 'qty_good';
            if (preg_match('/\bDEFECT\b/', $n)) return 'qty_defect';
            if (preg_match('/\bDAMAGED\b/', $n)) return 'qty_damaged';
            return null;
        };

        // helper: parse summary rack breakdown from note
        // format produced by applyInOutInternal():
        // | RACKS:12(GOOD=3;DEFECT=1),15(DAMAGED=2)
        $parseSummaryRacks = function (string $noteText): array {
            $out = []; // [rackId => ['GOOD'=>x,'DEFECT'=>y,'DAMAGED'=>z]]
            if (!preg_match('/\|\s*RACKS:\s*([^|]+)\s*/i', $noteText, $m)) {
                return $out;
            }

            $raw = trim((string)($m[1] ?? ''));
            if ($raw === '') return $out;

            foreach (explode(',', $raw) as $chunk) {
                $chunk = trim($chunk);
                if ($chunk === '') continue;

                if (!preg_match('/^(\d+)\((.+)\)$/', $chunk, $mm)) continue;
                $rid = (int)$mm[1];
                $inside = trim((string)$mm[2]);

                if ($rid <= 0 || $inside === '') continue;

                $out[$rid] = $out[$rid] ?? ['GOOD' => 0, 'DEFECT' => 0, 'DAMAGED' => 0];

                foreach (explode(';', $inside) as $pair) {
                    $pair = trim($pair);
                    if ($pair === '') continue;

                    [$k, $v] = array_pad(explode('=', $pair, 2), 2, '0');
                    $k = strtoupper(trim($k));
                    $v = (int)trim($v);

                    if (!in_array($k, ['GOOD', 'DEFECT', 'DAMAGED'], true)) continue;
                    if ($v <= 0) continue;

                    $out[$rid][$k] = (int)$out[$rid][$k] + $v;
                }
            }

            return $out;
        };

        foreach ($mutations as $m) {

            // 1) rollback STOCK header
            $stock = Stock::withoutGlobalScopes()
                ->where('branch_id', (int)$m->branch_id)
                ->where('warehouse_id', (int)$m->warehouse_id)
                ->where('product_id', (int)$m->product_id)
                ->lockForUpdate()
                ->first();

            if (!$stock) {
                $stock = Stock::create([
                    'branch_id'     => (int)$m->branch_id,
                    'warehouse_id'  => (int)$m->warehouse_id,
                    'product_id'    => (int)$m->product_id,
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
                'qty_available' => (int)($m->stock_early ?? 0),
                'updated_by'    => auth()->id(),
            ]);

            // 2) rollback STOCK_RACKS
            $noteText = (string)($m->note ?? '');
            $isSummary = str_starts_with(trim($noteText), '[SUMMARY]');

            $mutationTypeUpper = strtoupper((string)$m->mutation_type); // 'IN'/'OUT'/'TRANSFER' (case-insensitive)
            $isIn  = $mutationTypeUpper === 'IN';
            $isOut = $mutationTypeUpper === 'OUT';

            if ($isSummary && ($isIn || $isOut)) {
                // summary row: rack_id is null => rollback per rack using RACKS breakdown in note
                $rackMap = $parseSummaryRacks($noteText);

                foreach ($rackMap as $rackId => $buckets) {
                    $rackId = (int)$rackId;
                    if ($rackId <= 0) continue;

                    $goodQty   = (int)($buckets['GOOD'] ?? 0);
                    $defQty    = (int)($buckets['DEFECT'] ?? 0);
                    $damQty    = (int)($buckets['DAMAGED'] ?? 0);
                    $totalQty  = $goodQty + $defQty + $damQty;

                    if ($totalQty <= 0) continue;

                    // sign rollback:
                    // original IN  => stock_racks dulu +qty, rollback harus -qty
                    // original OUT => stock_racks dulu -qty, rollback harus +qty
                    $sign = $isIn ? -1 : +1;

                    $sr = DB::table('stock_racks')
                        ->where('branch_id', (int)$m->branch_id)
                        ->where('warehouse_id', (int)$m->warehouse_id)
                        ->where('rack_id', (int)$rackId)
                        ->where('product_id', (int)$m->product_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$sr) {
                        // kalau row tidak ada, create minimal row supaya update aman
                        DB::table('stock_racks')->insert([
                            'branch_id'     => (int)$m->branch_id,
                            'warehouse_id'  => (int)$m->warehouse_id,
                            'rack_id'       => (int)$rackId,
                            'product_id'    => (int)$m->product_id,
                            'qty_available' => 0,
                            'qty_good'      => 0,
                            'qty_defect'    => 0,
                            'qty_damaged'   => 0,
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);

                        $sr = (object)[
                            'qty_available' => 0,
                            'qty_good'      => 0,
                            'qty_defect'    => 0,
                            'qty_damaged'   => 0,
                        ];
                    }

                    $curAvail = (int)($sr->qty_available ?? 0);
                    $curGood  = (int)($sr->qty_good ?? 0);
                    $curDef   = (int)($sr->qty_defect ?? 0);
                    $curDam   = (int)($sr->qty_damaged ?? 0);

                    $newAvail = $curAvail + ($sign * $totalQty);
                    $newGood  = $curGood  + ($sign * $goodQty);
                    $newDef   = $curDef   + ($sign * $defQty);
                    $newDam   = $curDam   + ($sign * $damQty);

                    // safety
                    if ($newAvail < 0) $newAvail = 0;
                    if ($newGood  < 0) $newGood  = 0;
                    if ($newDef   < 0) $newDef   = 0;
                    if ($newDam   < 0) $newDam   = 0;

                    DB::table('stock_racks')
                        ->where('branch_id', (int)$m->branch_id)
                        ->where('warehouse_id', (int)$m->warehouse_id)
                        ->where('rack_id', (int)$rackId)
                        ->where('product_id', (int)$m->product_id)
                        ->update([
                            'qty_available' => (int)$newAvail,
                            'qty_good'      => (int)$newGood,
                            'qty_defect'    => (int)$newDef,
                            'qty_damaged'   => (int)$newDam,
                            'updated_at'    => now(),
                        ]);
                }
            } else {
                // non-summary: use rack_id column if exists
                $rackId = (int)($m->rack_id ?? 0);
                if ($rackId > 0 && ($isIn || $isOut)) {

                    $qty = $isIn ? (int)($m->stock_in ?? 0) : (int)($m->stock_out ?? 0);
                    if ($qty > 0) {
                        $bucketCol = $resolveBucketFromNote($noteText);

                        $sr = DB::table('stock_racks')
                            ->where('branch_id', (int)$m->branch_id)
                            ->where('warehouse_id', (int)$m->warehouse_id)
                            ->where('rack_id', (int)$rackId)
                            ->where('product_id', (int)$m->product_id)
                            ->lockForUpdate()
                            ->first();

                        if ($sr) {
                            $curAvail = (int)($sr->qty_available ?? 0);

                            // reverse delta:
                            // original IN  => rollback -qty
                            // original OUT => rollback +qty
                            $newAvail = $isIn ? ($curAvail - $qty) : ($curAvail + $qty);
                            if ($newAvail < 0) $newAvail = 0;

                            $update = [
                                'qty_available' => (int)$newAvail,
                                'updated_at'    => now(),
                            ];

                            if ($bucketCol && in_array($bucketCol, ['qty_good','qty_defect','qty_damaged'], true)) {
                                $curBucket = (int)($sr->{$bucketCol} ?? 0);
                                $newBucket = $isIn ? ($curBucket - $qty) : ($curBucket + $qty);
                                if ($newBucket < 0) $newBucket = 0;
                                $update[$bucketCol] = (int)$newBucket;
                            }

                            DB::table('stock_racks')
                                ->where('branch_id', (int)$m->branch_id)
                                ->where('warehouse_id', (int)$m->warehouse_id)
                                ->where('rack_id', (int)$rackId)
                                ->where('product_id', (int)$m->product_id)
                                ->update($update);
                        }
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
