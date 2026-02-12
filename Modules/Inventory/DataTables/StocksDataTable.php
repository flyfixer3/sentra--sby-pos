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
            // rowId internal (biar stabil)
            ->setRowId('stock_id')

            /**
             * ✅ Kolom pertama: Product ID (bukan stock_id lagi)
             */
            ->editColumn('product_id', function ($row) {
                return (int) ($row->product_id ?? 0);
            })

            /**
             * ✅ Gudang selalu All Warehouses badge
             */
            ->editColumn('warehouse_name', function ($row) {
                return '<span class="badge bg-primary rounded-pill px-3 py-1 text-white">All Warehouses</span>';
            })

            // Number formatting
            ->editColumn('total_qty', fn ($row) => number_format((int) ($row->total_qty ?? 0)))
            ->editColumn('good_qty', fn ($row) => number_format((int) ($row->good_qty ?? 0)))
            ->editColumn('reserved_qty', fn ($row) => number_format((int) ($row->reserved_qty ?? 0)))
            ->editColumn('incoming_qty', fn ($row) => number_format((int) ($row->incoming_qty ?? 0)))
            ->editColumn('available_qty', fn ($row) => number_format((int) ($row->available_qty ?? 0)))

            /**
             * ✅ Defect badge (kirim branch_id, bukan warehouse_id)
             */
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

            /**
             * ✅ Damaged badge (kirim branch_id, bukan warehouse_id)
             */
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

            /**
             * ✅ Action button: panggil showStockDetail() (match JS kamu)
             * Kirim juga reserved & incoming supaya modal header bisa tampil.
             */
            ->addColumn('action', function ($row) {
                $productId = (int) ($row->product_id ?? 0);
                $branchId  = (int) ($row->branch_id ?? 0);
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

    public function query(): \Illuminate\Database\Query\Builder
    {
        $activeBranch = session('active_branch');
        $isAllBranchMode = ($activeBranch === 'all');

        $productTerm = request()->filled('product') ? trim((string) request()->product) : null;

        // kalau all-branch mode, filter branch dari dropdown page (optional)
        $branchFilterId = request()->filled('branch_id') ? (int) request()->branch_id : null;

        // ===========================
        // DEFECT/DAMAGED AGG (by branch)
        // ===========================
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
        // ALL BRANCH MODE
        // ===========================
        if ($isAllBranchMode) {

            // resolve branch_id legacy: banyak stocks.branch_id NULL
            $resolvedBranchExpr = 'COALESCE(stocks.branch_id, warehouses.branch_id)';

            $q = DB::table('stocks')
                ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
                ->leftJoin('branches', 'branches.id', '=', DB::raw($resolvedBranchExpr))
                ->leftJoin('products', 'products.id', '=', 'stocks.product_id')
                ->leftJoinSub($defectAgg, 'defects', function ($join) use ($resolvedBranchExpr) {
                    $join->on('defects.product_id', '=', 'stocks.product_id')
                        ->on('defects.branch_id', '=', DB::raw($resolvedBranchExpr));
                })
                ->leftJoinSub($damagedAgg, 'damaged', function ($join) use ($resolvedBranchExpr) {
                    $join->on('damaged.product_id', '=', 'stocks.product_id')
                        ->on('damaged.branch_id', '=', DB::raw($resolvedBranchExpr));
                })
                ->select([
                    // rowId internal
                    DB::raw('MIN(stocks.id) as stock_id'),

                    // ✅ yang kita tampilkan
                    'stocks.product_id',
                    DB::raw($resolvedBranchExpr . ' as branch_id'),
                    DB::raw('1 as is_all_branch_mode'),

                    DB::raw('MAX(branches.name) as branch_name'),
                    DB::raw('MAX(products.product_code) as product_code'),
                    DB::raw('MAX(products.product_name) as product_name'),

                    DB::raw("'All Warehouses' as warehouse_name"),

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
                // penting: branch resolved harus ada
                ->whereRaw($resolvedBranchExpr . ' IS NOT NULL')
                ->groupBy('stocks.product_id', DB::raw($resolvedBranchExpr));

            /**
             * ✅ FIX DATATABLES ERROR:
             * jangan pakai havingRaw(stocks.branch_id ...)
             * pakai WHERE RAW COALESCE sebelum GROUP BY
             */
            if (!empty($branchFilterId)) {
                $q->whereRaw($resolvedBranchExpr . ' = ?', [$branchFilterId]);
            }

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
        // (tetap "All Warehouses row only")
        // ===========================
        $branchId = (int) $activeBranch;

        $q = DB::table('stocks')
            ->leftJoin('products', 'products.id', '=', 'stocks.product_id')
            ->leftJoin('branches', 'branches.id', '=', 'stocks.branch_id')
            ->leftJoinSub($defectAgg, 'defects', function ($join) {
                $join->on('defects.product_id', '=', 'stocks.product_id')
                    ->on('defects.branch_id', '=', 'stocks.branch_id');
            })
            ->leftJoinSub($damagedAgg, 'damaged', function ($join) {
                $join->on('damaged.product_id', '=', 'stocks.product_id')
                    ->on('damaged.branch_id', '=', 'stocks.branch_id');
            })
            ->where('stocks.branch_id', $branchId)
            ->select([
                DB::raw('MIN(stocks.id) as stock_id'),

                // ✅ tampil
                'stocks.product_id',
                'stocks.branch_id',
                DB::raw('0 as is_all_branch_mode'),

                DB::raw('MAX(branches.name) as branch_name'),
                DB::raw('MAX(products.product_code) as product_code'),
                DB::raw('MAX(products.product_name) as product_name'),
                DB::raw("'All Warehouses' as warehouse_name"),

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
            ->groupBy('stocks.product_id', 'stocks.branch_id');

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
                'order' => [[0, 'desc']],
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
            // ✅ Product ID column
            ['data' => 'product_id', 'name' => 'product_id', 'title' => 'Product ID', 'class' => 'text-start'],
        ];

        if ($isAllBranchMode) {
            $cols[] = ['data' => 'branch_name', 'name' => 'branch_name', 'title' => 'Branch'];
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
