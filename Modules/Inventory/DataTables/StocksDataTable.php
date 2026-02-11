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

            // ✅ Column: warehouse label (always All Warehouses)
            ->editColumn('warehouse_name', function ($row) {
                return '<span class="badge bg-primary rounded-pill px-3 py-1 text-white">All Warehouses</span>';
            })

            // ✅ Branch display only for all-branch mode
            ->editColumn('branch_name', function ($row) {
                return e((string) ($row->branch_name ?? '-'));
            })

            // ✅ Number formatting
            ->editColumn('total_qty', fn ($row) => number_format((int) ($row->total_qty ?? 0)))
            ->editColumn('good_qty', fn ($row) => number_format((int) ($row->good_qty ?? 0)))
            ->editColumn('reserved_qty', fn ($row) => number_format((int) ($row->reserved_qty ?? 0)))
            ->editColumn('incoming_qty', fn ($row) => number_format((int) ($row->incoming_qty ?? 0)))
            ->editColumn('available_qty', fn ($row) => number_format((int) ($row->available_qty ?? 0)))

            // ✅ defect badge (clickable)
            ->addColumn('defect_qty', function ($row) {
                $qty = (int) ($row->defect_qty ?? 0);
                $productId = (int) ($row->product_id ?? 0);

                $branchId = (int) ($row->branch_id ?? 0);
                $isAll = (int) ($row->is_all_branch_mode ?? 0);

                return '<a href="javascript:void(0)" class="text-decoration-none"
                            onclick="openQualityModal(\'defect\',' . $productId . ',' . $branchId . ',' . $isAll . ')">
                            <span class="badge bg-warning text-dark rounded-pill px-2">' . $qty . '</span>
                        </a>';
            })

            // ✅ damaged badge (clickable)
            ->addColumn('damaged_qty', function ($row) {
                $qty = (int) ($row->damaged_qty ?? 0);
                $productId = (int) ($row->product_id ?? 0);

                $branchId = (int) ($row->branch_id ?? 0);
                $isAll = (int) ($row->is_all_branch_mode ?? 0);

                return '<a href="javascript:void(0)" class="text-decoration-none"
                            onclick="openQualityModal(\'damaged\',' . $productId . ',' . $branchId . ',' . $isAll . ')">
                            <span class="badge bg-danger rounded-pill px-2">' . $qty . '</span>
                        </a>';
            })

            // ✅ action button: view detail modal
            ->addColumn('action', function ($row) {
                $productId = (int) ($row->product_id ?? 0);
                $branchId  = (int) ($row->branch_id ?? 0);

                // reserved/incoming for modal badge (static)
                $reserved  = (int) ($row->reserved_qty ?? 0);
                $incoming  = (int) ($row->incoming_qty ?? 0);

                if ($productId <= 0) return '-';

                return '
                    <button type="button"
                            class="btn btn-sm btn-outline-info rounded-pill px-3"
                            onclick="showStockDetail(' . $productId . ',' . $branchId . ',' . $reserved . ',' . $incoming . ')">
                        <i class="bi bi-eye"></i> View Detail
                    </button>
                ';
            })

            ->rawColumns(['warehouse_name', 'defect_qty', 'damaged_qty', 'action']);
    }

    /**
     * Query hanya “All Warehouses” row.
     *
     * - branch mode spesifik: group per product untuk 1 cabang
     * - all branch mode: group per product per branch
     */
    public function query(): \Illuminate\Database\Query\Builder
    {
        $activeBranch = session('active_branch');
        $isAllBranchMode = ($activeBranch === 'all');

        // Filter inputs:
        // - branch_id hanya muncul saat all-branch mode (di UI)
        $filterBranchId = request()->filled('branch_id') ? (int) request()->branch_id : null;
        $productTerm = request()->filled('product') ? trim((string) request()->product) : null;

        // defect agg
        $defAgg = DB::table('product_defect_items')
            ->whereNull('moved_out_at')
            ->selectRaw('product_id, branch_id, SUM(quantity) AS defect_qty')
            ->groupBy('product_id', 'branch_id');

        // damaged agg
        $damAgg = DB::table('product_damaged_items')
            ->where('resolution_status', 'pending')
            ->whereNull('moved_out_at')
            ->selectRaw('product_id, branch_id, SUM(quantity) AS damaged_qty')
            ->groupBy('product_id', 'branch_id');

        /**
         * Reserved & Incoming:
         * sesuai sistem kamu: dicatat di row stocks pool (warehouse_id NULL).
         * jadi kita ambil MAX dari stocks yang warehouse_id NULL per product+branch
         */
        $pool = DB::table('stocks')
            ->whereNull('warehouse_id')
            ->selectRaw('product_id, branch_id, MAX(id) as stock_id, MAX(qty_reserved) as reserved_qty, MAX(qty_incoming) as incoming_qty')
            ->groupBy('product_id', 'branch_id');

        /**
         * Total (fisik) dihitung dari stocks per warehouse (warehouse_id NOT NULL)
         * total_qty = SUM(qty_available)
         */
        $whAgg = DB::table('stocks')
            ->whereNotNull('warehouse_id')
            ->selectRaw('product_id, branch_id, SUM(qty_available) as total_qty')
            ->groupBy('product_id', 'branch_id');

        /**
         * Final row “All Warehouses”
         * stock_id kita ambil dari pool.stock_id (biar kolom pertama jadi “ID stocks”)
         * kalau pool tidak ada, fallback MIN(stocks.id) dari whAgg join stocks.
         */
        $q = DB::query()
            ->fromSub($whAgg, 'agg')
            ->select([
                DB::raw('COALESCE(pool.stock_id, 0) as stock_id'),
                'agg.product_id',
                'agg.branch_id',
                DB::raw('1 as is_branch_pool'),
                DB::raw(($isAllBranchMode ? '1' : '0') . ' as is_all_branch_mode'),

                DB::raw('MAX(products.product_code) as product_code'),
                DB::raw('MAX(products.product_name) as product_name'),
                DB::raw("'All Warehouses' as warehouse_name"),

                DB::raw('COALESCE(agg.total_qty,0) as total_qty'),
                DB::raw('COALESCE(pool.reserved_qty,0) as reserved_qty'),
                DB::raw('COALESCE(pool.incoming_qty,0) as incoming_qty'),

                DB::raw('COALESCE(defAgg.defect_qty,0) as defect_qty'),
                DB::raw('COALESCE(damAgg.damaged_qty,0) as damaged_qty'),

                // good = total - defect - damaged
                DB::raw('
                    GREATEST(
                        COALESCE(agg.total_qty,0)
                        - COALESCE(defAgg.defect_qty,0)
                        - COALESCE(damAgg.damaged_qty,0),
                        0
                    ) as good_qty
                '),

                // available = (total - damaged) - reserved_pool
                DB::raw('
                    GREATEST(
                        GREATEST(
                            COALESCE(agg.total_qty,0) - COALESCE(damAgg.damaged_qty,0),
                            0
                        ) - COALESCE(pool.reserved_qty,0),
                        0
                    ) as available_qty
                '),

                // branch name (only useful for all-branch UI)
                DB::raw('COALESCE(b.name, "-") as branch_name'),
            ])
            ->leftJoin('products', 'products.id', '=', 'agg.product_id')
            ->leftJoinSub($pool, 'pool', function ($join) {
                $join->on('pool.product_id', '=', 'agg.product_id')
                    ->on('pool.branch_id', '=', 'agg.branch_id');
            })
            ->leftJoinSub($defAgg, 'defAgg', function ($join) {
                $join->on('defAgg.product_id', '=', 'agg.product_id')
                    ->on('defAgg.branch_id', '=', 'agg.branch_id');
            })
            ->leftJoinSub($damAgg, 'damAgg', function ($join) {
                $join->on('damAgg.product_id', '=', 'agg.product_id')
                    ->on('damAgg.branch_id', '=', 'agg.branch_id');
            })
            ->leftJoin('branches as b', 'b.id', '=', 'agg.branch_id')
            ->groupBy(
                'agg.product_id',
                'agg.branch_id',
                'agg.total_qty',
                'pool.stock_id',
                'pool.reserved_qty',
                'pool.incoming_qty',
                'defAgg.defect_qty',
                'damAgg.damaged_qty',
                'b.name'
            );

        // ✅ branch scope
        if (!$isAllBranchMode && is_numeric($activeBranch)) {
            $q->where('agg.branch_id', (int) $activeBranch);
        }

        // ✅ all branch mode: optional filter branch_id dari UI
        if ($isAllBranchMode && !empty($filterBranchId)) {
            $q->where('agg.branch_id', $filterBranchId);
        }

        // ✅ search product
        if (!empty($productTerm)) {
            $term = '%' . $productTerm . '%';
            $q->where(function ($w) use ($term) {
                $w->where('products.product_name', 'like', $term)
                  ->orWhere('products.product_code', 'like', $term);
            });
        }

        return $q;
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
                'order' => [[0, 'desc']], // sort by stock_id desc
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
        $cols = [];

        // ✅ kolom pertama: ID dari stocks (pool row)
        $cols[] = [
            'data' => 'stock_id',
            'name' => 'stock_id',
            'title' => 'ID',
            'class' => 'text-end',
        ];

        if ($isAllBranchMode) {
            $cols[] = [
                'data' => 'branch_name',
                'name' => 'branch_name',
                'title' => 'Branch',
            ];
        }

        $cols[] = ['data' => 'product_code', 'name' => 'product_code', 'title' => 'Kode Produk'];
        $cols[] = ['data' => 'product_name', 'name' => 'product_name', 'title' => 'Nama Produk'];
        $cols[] = ['data' => 'warehouse_name', 'name' => 'warehouse_name', 'title' => 'Gudang'];

        $cols[] = ['data' => 'total_qty', 'name' => 'total_qty', 'title' => 'Total', 'class' => 'text-end'];
        $cols[] = ['data' => 'good_qty', 'name' => 'good_qty', 'title' => 'Good', 'class' => 'text-end'];
        $cols[] = ['data' => 'reserved_qty', 'name' => 'reserved_qty', 'title' => 'Reserved', 'class' => 'text-end'];
        $cols[] = ['data' => 'available_qty', 'name' => 'available_qty', 'title' => 'Available', 'class' => 'text-end'];
        $cols[] = ['data' => 'incoming_qty', 'name' => 'incoming_qty', 'title' => 'Incoming', 'class' => 'text-end'];

        $cols[] = ['data' => 'defect_qty', 'name' => 'defect_qty', 'title' => 'Defect', 'class' => 'text-end', 'orderable' => false];
        $cols[] = ['data' => 'damaged_qty', 'name' => 'damaged_qty', 'title' => 'Damaged', 'class' => 'text-end', 'orderable' => false];

        $cols[] = [
            'data' => 'action',
            'name' => 'action',
            'title' => 'Detail',
            'orderable' => false,
            'searchable' => false,
            'class' => 'text-center',
        ];

        return $cols;
    }

    protected function filename(): string
    {
        return 'Stocks_' . date('YmdHis');
    }
}
