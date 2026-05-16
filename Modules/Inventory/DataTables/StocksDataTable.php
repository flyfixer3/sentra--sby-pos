<?php

namespace Modules\Inventory\DataTables;

use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Html\Button;
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
            ->editColumn('available_qty', function ($row) {
                $qty = (int) ($row->available_qty ?? 0);

                return '<span class="badge bg-success rounded-pill px-3 py-1 text-white">'
                    . number_format($qty)
                    . '</span>';
            })
            ->orderColumn('defect_qty', function ($query, $order) {
                return $this->orderByNumericAlias($query, 'defect_qty', $order);
            })
            ->orderColumn('damaged_qty', function ($query, $order) {
                return $this->orderByNumericAlias($query, 'damaged_qty', $order);
            })

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

            ->rawColumns(['warehouse_name', 'available_qty', 'defect_qty', 'damaged_qty', 'action']);
    }

    public function query(): \Illuminate\Database\Query\Builder
    {
        $activeBranch = session('active_branch');
        $isAllBranchMode = ($activeBranch === 'all');

        $productTerm = request()->filled('product') ? trim((string) request()->product) : null;
        $productTerm = $productTerm !== '' ? $productTerm : null;

        // kalau all-branch mode, filter branch dari dropdown page (optional)
        $branchFilterId = request()->filled('branch_id') ? (int) request()->branch_id : null;

        $targetBranchId = $isAllBranchMode ? $branchFilterId : (int) $activeBranch;
        $canFallbackToProducts = !empty($productTerm) && !empty($targetBranchId);
        $resolvedStockBranchExpr = 'COALESCE(stocks.branch_id, warehouses.branch_id)';

        $stockAgg = DB::table('stocks')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->whereRaw($resolvedStockBranchExpr . ' IS NOT NULL')
            ->select([
                'stocks.product_id',
                DB::raw($resolvedStockBranchExpr . ' as branch_id'),
                DB::raw('MIN(stocks.id) as stock_id'),
                DB::raw('COALESCE(SUM(stocks.qty_total), 0) as total_qty'),
                DB::raw('COALESCE(SUM(stocks.qty_reserved), 0) as reserved_qty'),
                DB::raw('COALESCE(SUM(stocks.qty_incoming), 0) as incoming_qty'),
            ])
            ->groupBy('stocks.product_id', DB::raw($resolvedStockBranchExpr));

        if (!empty($targetBranchId)) {
            $stockAgg->whereRaw($resolvedStockBranchExpr . ' = ?', [$targetBranchId]);
        }

        $defectAgg = DB::table('product_defect_items')
            ->whereNull('moved_out_at')
            ->selectRaw('product_id, branch_id, SUM(quantity) AS defect_qty')
            ->groupBy('product_id', 'branch_id');

        $damagedAgg = DB::table('product_damaged_items')
            ->where('resolution_status', 'pending')
            ->whereNull('moved_out_at')
            ->selectRaw('product_id, branch_id, SUM(quantity) AS damaged_qty')
            ->groupBy('product_id', 'branch_id');

        $branchJoinExpr = !empty($targetBranchId)
            ? DB::raw((string) $targetBranchId)
            : DB::raw('stock_agg.branch_id');

        $q = DB::table('products')
            ->leftJoinSub($stockAgg, 'stock_agg', function ($join) {
                $join->on('stock_agg.product_id', '=', 'products.id');
            })
            ->leftJoin('branches', 'branches.id', '=', $branchJoinExpr)
            ->leftJoinSub($defectAgg, 'defects', function ($join) use ($branchJoinExpr) {
                $join->on('defects.product_id', '=', 'products.id')
                    ->on('defects.branch_id', '=', $branchJoinExpr);
            })
            ->leftJoinSub($damagedAgg, 'damaged', function ($join) use ($branchJoinExpr) {
                $join->on('damaged.product_id', '=', 'products.id')
                    ->on('damaged.branch_id', '=', $branchJoinExpr);
            })
            ->select([
                DB::raw(!empty($targetBranchId)
                    ? "COALESCE(stock_agg.stock_id, CONCAT('product-', products.id, '-branch-', " . (int) $targetBranchId . ')) as stock_id'
                    : 'stock_agg.stock_id as stock_id'),
                DB::raw('products.id as product_id'),
                DB::raw(!empty($targetBranchId) ? ((int) $targetBranchId . ' as branch_id') : 'stock_agg.branch_id as branch_id'),
                DB::raw($isAllBranchMode ? '1 as is_all_branch_mode' : '0 as is_all_branch_mode'),
                DB::raw('branches.name as branch_name'),
                DB::raw('products.product_code as product_code'),
                DB::raw('products.product_name as product_name'),
                DB::raw("'All Warehouses' as warehouse_name"),
                DB::raw('COALESCE(stock_agg.total_qty, 0) as total_qty'),
                DB::raw('COALESCE(stock_agg.reserved_qty, 0) as reserved_qty'),
                DB::raw('COALESCE(stock_agg.incoming_qty, 0) as incoming_qty'),
                DB::raw('0 as outgoing_qty'),
                DB::raw('COALESCE(defects.defect_qty, 0) as defect_qty'),
                DB::raw('COALESCE(damaged.damaged_qty, 0) as damaged_qty'),
                DB::raw('
                    GREATEST(
                        COALESCE(stock_agg.total_qty, 0)
                        - COALESCE(defects.defect_qty,0)
                        - COALESCE(damaged.damaged_qty,0),
                        0
                    ) as good_qty
                '),
                DB::raw('
                    GREATEST(
                        (
                            GREATEST(
                                COALESCE(stock_agg.total_qty, 0) - COALESCE(damaged.damaged_qty,0),
                                0
                            )
                        ) - COALESCE(stock_agg.reserved_qty, 0),
                        0
                    ) as available_qty
                '),
            ]);

        if (!empty($productTerm)) {
            $term = '%' . $productTerm . '%';
            $q->where(function ($w) use ($term) {
                $w->where('products.product_name', 'like', $term)
                    ->orWhere('products.product_code', 'like', $term);
            });
        }

        if (!$canFallbackToProducts) {
            $q->whereNotNull('stock_agg.stock_id');
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
            ->dom(
                "<'stock-table-toolbar'<'stock-table-length'l><'stock-table-actions'B>>" .
                "tr" .
                "<'row'<'col-md-5'i><'col-md-7 mt-2'p>>"
            )
            ->buttons(
                Button::make('excel')->text('<i class="bi bi-file-earmark-excel-fill"></i> Excel'),
                Button::make('print')->text('<i class="bi bi-printer-fill"></i> Print'),
                Button::make('reset')->text('<i class="bi bi-x-circle"></i> Reset'),
                Button::make('reload')->text('<i class="bi bi-arrow-repeat"></i> Reload')
            )
            ->parameters([
                'scrollX' => true,
                'responsive' => false,
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

        $cols[] = ['data' => 'defect_qty', 'name' => 'defect_qty', 'title' => 'Defect', 'class' => 'text-end'];
        $cols[] = ['data' => 'damaged_qty', 'name' => 'damaged_qty', 'title' => 'Damaged', 'class' => 'text-end'];

        $cols[] = [
            'data' => 'action',
            'name' => 'action',
            'title' => 'Detail',
            'orderable' => false,
            'searchable' => false,
            'exportable' => false,
            'printable' => false,
            'class' => 'text-center',
        ];

        return $cols;
    }

    protected function filename(): string
    {
        return 'Stocks_' . date('YmdHis');
    }

    private function orderByNumericAlias($query, string $alias, string $order)
    {
        $allowedAliases = ['defect_qty', 'damaged_qty'];
        if (!in_array($alias, $allowedAliases, true)) {
            return $query;
        }

        $direction = strtolower($order) === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw($alias . ' ' . $direction);
    }
}
