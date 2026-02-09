<?php

namespace Modules\Inventory\DataTables;

use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\QueryDataTable;

class StocksDataTable extends DataTable
{
    public function dataTable($query): QueryDataTable
    {
        return (new QueryDataTable($query))
            ->addIndexColumn()

            // Format gudang: All Warehouses -> badge
            ->editColumn('warehouse_name', function ($row) {
                $isPool = (int) ($row->is_branch_pool ?? 0);

                if ($isPool === 1) {
                    return '<span class="badge bg-primary rounded-pill px-3 py-1 text-white">All Warehouses</span>';
                }

                return e((string) ($row->warehouse_name ?? '-'));
            })

            // Number formatting
            ->editColumn('total_qty', fn ($row) => number_format((int) ($row->total_qty ?? 0)))
            ->editColumn('good_qty', fn ($row) => number_format((int) ($row->good_qty ?? 0)))

            // Reserved/Incoming:
            // - tampil angka hanya di summary row (All Warehouses)
            // - row gudang tampil "-"
            ->editColumn('reserved_qty', function ($row) {
                $isPool = (int) ($row->is_branch_pool ?? 0);
                if ($isPool !== 1) return '-';
                return number_format((int) ($row->reserved_qty ?? 0));
            })
            ->editColumn('incoming_qty', function ($row) {
                $isPool = (int) ($row->is_branch_pool ?? 0);
                if ($isPool !== 1) return '-';
                return number_format((int) ($row->incoming_qty ?? 0));
            })

            ->editColumn('available_qty', fn ($row) => number_format((int) ($row->available_qty ?? 0)))

            // defect badge
            ->addColumn('defect_qty', function ($row) {
                $qty = (int) ($row->defect_qty ?? 0);
                $productId = (int) ($row->product_id ?? 0);
                $warehouseId = (int) ($row->warehouse_id ?? 0);
                $isAll = (int) ($row->is_all_branch_mode ?? 0);

                // jika summary row, warehouseId 0 => modal tetap boleh (anggap all)
                return '<a href="javascript:void(0)" class="text-decoration-none"
                            onclick="openQualityModal(\'defect\',' . $productId . ',' . $warehouseId . ',' . $isAll . ')">
                            <span class="badge bg-warning text-dark rounded-pill px-2">' . $qty . '</span>
                        </a>';
            })

            // damaged badge
            ->addColumn('damaged_qty', function ($row) {
                $qty = (int) ($row->damaged_qty ?? 0);
                $productId = (int) ($row->product_id ?? 0);
                $warehouseId = (int) ($row->warehouse_id ?? 0);
                $isAll = (int) ($row->is_all_branch_mode ?? 0);

                return '<a href="javascript:void(0)" class="text-decoration-none"
                            onclick="openQualityModal(\'damaged\',' . $productId . ',' . $warehouseId . ',' . $isAll . ')">
                            <span class="badge bg-danger rounded-pill px-2">' . $qty . '</span>
                        </a>';
            })

            // action button
            ->addColumn('action', function ($row) {
                // all branch mode OR summary row => no rack button
                if ((int) ($row->is_all_branch_mode ?? 0) === 1) return '-';
                if ((int) ($row->is_branch_pool ?? 0) === 1) return '-';

                $productId = (int) ($row->product_id ?? 0);
                $branchId = (int) ($row->branch_id ?? 0);
                $warehouseId = (int) ($row->warehouse_id ?? 0);

                if ($productId <= 0 || $branchId <= 0 || $warehouseId <= 0) return '-';

                return '
                    <button type="button"
                            class="btn btn-sm btn-outline-info rounded-pill px-3"
                            onclick="showRackDetails(' . $productId . ',' . $branchId . ',' . $warehouseId . ')">
                        <i class="bi bi-eye"></i> Rack
                    </button>
                ';
            })

            // row class: highlight All Warehouses row
            ->setRowClass(function ($row) {
                return ((int) ($row->is_branch_pool ?? 0) === 1)
                    ? 'table-primary fw-semibold'
                    : '';
            })

            ->rawColumns(['warehouse_name', 'defect_qty', 'damaged_qty', 'action']);
    }

    /**
     * NOTE:
     * - "Specific branch mode": show per warehouse rows + summary row "All Warehouses".
     * - "All branch mode": keep existing behavior (group per product).
     */
    public function query(): \Illuminate\Database\Query\Builder
    {
        $activeBranch = session('active_branch');
        $isAllBranchMode = ($activeBranch === 'all');

        $warehouseId = request()->filled('warehouse_id') ? (int) request()->warehouse_id : null;
        $productTerm = request()->filled('product') ? trim((string) request()->product) : null;

        // ===== defect/damaged base
        $defectSubPerWH = DB::table('product_defect_items')
            ->whereNull('moved_out_at')
            ->selectRaw('product_id, branch_id, warehouse_id, SUM(quantity) AS defect_qty')
            ->groupBy('product_id', 'branch_id', 'warehouse_id');

        $damagedSubPerWH = DB::table('product_damaged_items')
            ->where('resolution_status', 'pending')
            ->whereNull('moved_out_at')
            ->selectRaw('product_id, branch_id, warehouse_id, SUM(quantity) AS damaged_qty')
            ->groupBy('product_id', 'branch_id', 'warehouse_id');

        $defectAgg = DB::table('product_defect_items')
            ->whereNull('moved_out_at')
            ->selectRaw('product_id, branch_id, SUM(quantity) AS defect_qty')
            ->groupBy('product_id', 'branch_id');

        $damagedAgg = DB::table('product_damaged_items')
            ->where('resolution_status', 'pending')
            ->whereNull('moved_out_at')
            ->selectRaw('product_id, branch_id, SUM(quantity) AS damaged_qty')
            ->groupBy('product_id', 'branch_id');

        // ===========================
        // ALL BRANCH MODE (existing idea)
        // ===========================
        if ($isAllBranchMode) {
            $q = DB::table('stocks')
                ->select([
                    'stocks.product_id',
                    DB::raw('NULL as branch_id'),
                    DB::raw('NULL as warehouse_id'),
                    DB::raw('1 as is_all_branch_mode'),
                    DB::raw('0 as is_branch_pool'),
                    DB::raw('0 as row_order'),

                    DB::raw('MAX(products.product_code) as product_code'),
                    DB::raw('MAX(products.product_name) as product_name'),
                    DB::raw("CASE WHEN COUNT(DISTINCT stocks.warehouse_id) = 1 THEN MAX(warehouses.warehouse_name) ELSE 'MULTI' END as warehouse_name"),

                    DB::raw('SUM(stocks.qty_available) as total_qty'),
                    DB::raw('SUM(stocks.qty_reserved) as reserved_qty'),
                    DB::raw('SUM(stocks.qty_incoming) as incoming_qty'),

                    DB::raw('COALESCE(defects.defect_qty, 0) as defect_qty'),
                    DB::raw('COALESCE(damaged.damaged_qty, 0) as damaged_qty'),

                    DB::raw('
                        GREATEST(
                            SUM(stocks.qty_available)
                            - COALESCE(defects.defect_qty,0)
                            - COALESCE(damaged.damaged_qty,0),
                            0
                        ) as good_qty
                    '),

                    DB::raw('
                        GREATEST(
                            (
                                GREATEST(
                                    SUM(stocks.qty_available) - COALESCE(damaged.damaged_qty,0),
                                    0
                                )
                            ) - SUM(stocks.qty_reserved),
                            0
                        ) as available_qty
                    '),
                ])
                ->leftJoin('products', 'products.id', '=', 'stocks.product_id')
                ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
                ->leftJoinSub(
                    DB::table('product_defect_items')->whereNull('moved_out_at')->selectRaw('product_id, SUM(quantity) AS defect_qty')->groupBy('product_id'),
                    'defects',
                    fn ($join) => $join->on('defects.product_id', '=', 'stocks.product_id')
                )
                ->leftJoinSub(
                    DB::table('product_damaged_items')->where('resolution_status', 'pending')->whereNull('moved_out_at')->selectRaw('product_id, SUM(quantity) AS damaged_qty')->groupBy('product_id'),
                    'damaged',
                    fn ($join) => $join->on('damaged.product_id', '=', 'stocks.product_id')
                )
                ->groupBy('stocks.product_id');

            if (!empty($warehouseId)) $q->where('stocks.warehouse_id', $warehouseId);

            if (!empty($productTerm)) {
                $term = '%' . $productTerm . '%';
                $q->where(function ($w) use ($term) {
                    $w->where('products.product_name', 'like', $term)
                      ->orWhere('products.product_code', 'like', $term);
                });
            }

            return $q;
        }

        // ===========================
        // SPECIFIC BRANCH MODE
        // ===========================
        $branchId = (int) $activeBranch;

        // (A) per-warehouse rows (warehouse_id NOT NULL)
        $perWarehouse = DB::table('stocks')
            ->where('stocks.branch_id', $branchId)
            ->whereNotNull('stocks.warehouse_id')
            ->select([
                'stocks.product_id',
                'stocks.branch_id',
                'stocks.warehouse_id',
                DB::raw('0 as is_all_branch_mode'),
                DB::raw('0 as is_branch_pool'),
                DB::raw('0 as row_order'),

                DB::raw('MAX(products.product_code) as product_code'),
                DB::raw('MAX(products.product_name) as product_name'),
                DB::raw('MAX(warehouses.warehouse_name) as warehouse_name'),

                DB::raw('SUM(stocks.qty_available) as total_qty'),

                // reserved/incoming per warehouse (harusnya 0 kalau kamu pakai pool)
                DB::raw('SUM(stocks.qty_reserved) as reserved_qty'),
                DB::raw('SUM(stocks.qty_incoming) as incoming_qty'),

                DB::raw('COALESCE(defects.defect_qty, 0) as defect_qty'),
                DB::raw('COALESCE(damaged.damaged_qty, 0) as damaged_qty'),

                DB::raw('
                    GREATEST(
                        SUM(stocks.qty_available)
                        - COALESCE(defects.defect_qty,0)
                        - COALESCE(damaged.damaged_qty,0),
                        0
                    ) as good_qty
                '),

                // available per warehouse (tidak dikurangi reserved pool)
                DB::raw('
                    GREATEST(
                        GREATEST(
                            SUM(stocks.qty_available) - COALESCE(damaged.damaged_qty,0),
                            0
                        ) - SUM(stocks.qty_reserved),
                        0
                    ) as available_qty
                '),
            ])
            ->leftJoin('products', 'products.id', '=', 'stocks.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->leftJoinSub($defectSubPerWH, 'defects', function ($join) {
                $join->on('defects.product_id', '=', 'stocks.product_id')
                    ->on('defects.branch_id', '=', 'stocks.branch_id')
                    ->on('defects.warehouse_id', '=', 'stocks.warehouse_id');
            })
            ->leftJoinSub($damagedSubPerWH, 'damaged', function ($join) {
                $join->on('damaged.product_id', '=', 'stocks.product_id')
                    ->on('damaged.branch_id', '=', 'stocks.branch_id')
                    ->on('damaged.warehouse_id', '=', 'stocks.warehouse_id');
            })
            ->groupBy('stocks.product_id', 'stocks.branch_id', 'stocks.warehouse_id');

        if (!empty($warehouseId)) {
            $perWarehouse->where('stocks.warehouse_id', $warehouseId);
        }

        if (!empty($productTerm)) {
            $term = '%' . $productTerm . '%';
            $perWarehouse->where(function ($w) use ($term) {
                $w->where('products.product_name', 'like', $term)
                  ->orWhere('products.product_code', 'like', $term);
            });
        }

        // Jika user filter gudang, jangan munculin summary "All Warehouses"
        if (!empty($warehouseId)) {
            return $perWarehouse;
        }

        // (B) summary row per product: All Warehouses
        $whAgg = DB::table('stocks')
            ->where('branch_id', $branchId)
            ->whereNotNull('warehouse_id')
            ->selectRaw('product_id, branch_id, SUM(qty_available) as total_qty')
            ->groupBy('product_id', 'branch_id');

        $pool = DB::table('stocks')
            ->where('branch_id', $branchId)
            ->whereNull('warehouse_id')
            ->selectRaw('product_id, branch_id, MAX(qty_reserved) as reserved_qty, MAX(qty_incoming) as incoming_qty')
            ->groupBy('product_id', 'branch_id');

        $summary = DB::query()
            ->fromSub($whAgg, 'agg')
            ->select([
                'agg.product_id',
                'agg.branch_id',
                DB::raw('NULL as warehouse_id'),
                DB::raw('0 as is_all_branch_mode'),
                DB::raw('1 as is_branch_pool'),
                DB::raw('1 as row_order'),

                DB::raw('MAX(products.product_code) as product_code'),
                DB::raw('MAX(products.product_name) as product_name'),
                DB::raw("'All Warehouses' as warehouse_name"),

                DB::raw('COALESCE(agg.total_qty,0) as total_qty'),
                DB::raw('COALESCE(pool.reserved_qty,0) as reserved_qty'),
                DB::raw('COALESCE(pool.incoming_qty,0) as incoming_qty'),

                DB::raw('COALESCE(defAgg.defect_qty,0) as defect_qty'),
                DB::raw('COALESCE(damAgg.damaged_qty,0) as damaged_qty'),

                DB::raw('
                    GREATEST(
                        COALESCE(agg.total_qty,0)
                        - COALESCE(defAgg.defect_qty,0)
                        - COALESCE(damAgg.damaged_qty,0),
                        0
                    ) as good_qty
                '),

                // available summary = (total - damaged) - reserved_pool
                DB::raw('
                    GREATEST(
                        GREATEST(
                            COALESCE(agg.total_qty,0) - COALESCE(damAgg.damaged_qty,0),
                            0
                        ) - COALESCE(pool.reserved_qty,0),
                        0
                    ) as available_qty
                '),
            ])
            ->leftJoin('products', 'products.id', '=', 'agg.product_id')
            ->leftJoinSub($pool, 'pool', function ($join) {
                $join->on('pool.product_id', '=', 'agg.product_id')
                    ->on('pool.branch_id', '=', 'agg.branch_id');
            })
            ->leftJoinSub($defectAgg, 'defAgg', function ($join) {
                $join->on('defAgg.product_id', '=', 'agg.product_id')
                    ->on('defAgg.branch_id', '=', 'agg.branch_id');
            })
            ->leftJoinSub($damagedAgg, 'damAgg', function ($join) {
                $join->on('damAgg.product_id', '=', 'agg.product_id')
                    ->on('damAgg.branch_id', '=', 'agg.branch_id');
            })
            ->groupBy('agg.product_id', 'agg.branch_id', 'agg.total_qty', 'pool.reserved_qty', 'pool.incoming_qty', 'defAgg.defect_qty', 'damAgg.damaged_qty');

        if (!empty($productTerm)) {
            $term = '%' . $productTerm . '%';
            $summary->where(function ($w) use ($term) {
                $w->where('products.product_name', 'like', $term)
                  ->orWhere('products.product_code', 'like', $term);
            });
        }

        // UNION: per warehouse + summary
        return $perWarehouse->unionAll($summary);
    }

    public function html()
    {
        $isAllBranchMode = (session('active_branch') === 'all');

        return $this->builder()
            ->setTableId('stocks-table')
            ->columns($this->getColumns($isAllBranchMode))
            ->minifiedAjax()
            ->parameters([
                'responsive' => true,
                'autoWidth' => false,
                'processing' => true,
                'serverSide' => true,
                'pageLength' => 50,
                'lengthMenu' => [25, 50, 100],
                'searching' => false,
                'order' => $isAllBranchMode
                    ? [[1, 'asc']]
                    : [[1, 'asc'], [12, 'asc']], // row_order hidden (warehouse rows first, summary last)

                'language' => [
                    'lengthMenu' => 'Show _MENU_',
                    'info' => 'Showing _START_ - _END_ of _TOTAL_',
                    'paginate' => [
                        'previous' => 'Previous',
                        'next' => 'Next',
                    ],
                ],
            ]);
    }

    protected function getColumns(bool $isAllBranchMode): array
    {
        $cols = [
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => '#', 'orderable' => false, 'searchable' => false],
            ['data' => 'product_code', 'name' => 'product_code', 'title' => 'Kode Produk'],
            ['data' => 'product_name', 'name' => 'product_name', 'title' => 'Nama Produk'],
            ['data' => 'warehouse_name', 'name' => 'warehouse_name', 'title' => 'Gudang'],

            ['data' => 'total_qty', 'name' => 'total_qty', 'title' => 'Total', 'class' => 'text-end'],
            ['data' => 'good_qty', 'name' => 'good_qty', 'title' => 'Good', 'class' => 'text-end'],
            ['data' => 'reserved_qty', 'name' => 'reserved_qty', 'title' => 'Reserved', 'class' => 'text-end'],
            ['data' => 'available_qty', 'name' => 'available_qty', 'title' => 'Available', 'class' => 'text-end'],
            ['data' => 'incoming_qty', 'name' => 'incoming_qty', 'title' => 'Incoming', 'class' => 'text-end'],

            ['data' => 'defect_qty', 'name' => 'defect_qty', 'title' => 'Defect', 'class' => 'text-end', 'orderable' => false],
            ['data' => 'damaged_qty', 'name' => 'damaged_qty', 'title' => 'Damaged', 'class' => 'text-end', 'orderable' => false],
        ];

        if (!$isAllBranchMode) {
            $cols[] = [
                'data' => 'action',
                'name' => 'action',
                'title' => 'Detail',
                'orderable' => false,
                'searchable' => false,
                'class' => 'text-center',
            ];

            // hidden order helper
            $cols[] = [
                'data' => 'row_order',
                'name' => 'row_order',
                'visible' => false,
                'searchable' => false,
            ];
        }

        return $cols;
    }

    protected function filename(): string
    {
        return 'Stocks_' . date('YmdHis');
    }
}
