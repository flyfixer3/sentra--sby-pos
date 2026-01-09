<?php

namespace Modules\Inventory\DataTables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Entities\Stock;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Services\DataTable;

class StocksDataTable extends DataTable
{
    public function dataTable($query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()

            // number formatting
            ->editColumn('total_qty', fn ($row) => number_format((int) ($row->total_qty ?? 0)))
            ->editColumn('good_qty', fn ($row) => number_format((int) ($row->good_qty ?? 0)))

            // clickable defect badge -> modal
            ->addColumn('defect_qty', function ($row) {
                $qty = (int) ($row->defect_qty ?? 0);
                $productId = (int) ($row->product_id ?? 0);
                $warehouseId = (int) ($row->warehouse_id ?? 0);
                $isAll = (int) ($row->is_all_branch_mode ?? 0);

                return '<a href="javascript:void(0)" class="text-decoration-none"
                            onclick="openQualityModal(\'defect\',' . $productId . ',' . $warehouseId . ',' . $isAll . ')">
                            <span class="badge bg-warning text-dark rounded-pill px-2">' . $qty . '</span>
                        </a>';
            })

            // clickable damaged badge -> modal
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

            // rack detail button (only when specific branch)
            ->addColumn('action', function ($row) {
                if ((int) ($row->is_all_branch_mode ?? 0) === 1) {
                    return '-';
                }

                $productId = (int) ($row->product_id ?? 0);
                $branchId = (int) ($row->branch_id ?? 0);
                $warehouseId = (int) ($row->warehouse_id ?? 0);

                if ($productId <= 0 || $branchId <= 0 || $warehouseId <= 0) {
                    return '-';
                }

                return '
                    <button type="button"
                            class="btn btn-sm btn-outline-info rounded-pill px-3"
                            onclick="showRackDetails(' . $productId . ',' . $branchId . ',' . $warehouseId . ')">
                        <i class="bi bi-eye"></i> Rack
                    </button>
                ';
            })
            ->rawColumns(['defect_qty', 'damaged_qty', 'action']);
    }

    public function query(Stock $model): Builder
    {
        $activeBranch = session('active_branch');
        $isAllBranchMode = ($activeBranch === 'all');

        $warehouseId = request()->filled('warehouse_id') ? (int) request()->warehouse_id : null;
        $productTerm = request()->filled('product') ? trim((string) request()->product) : null;

        /**
         * Subquery defect/damaged:
         * - defect: exclude moved_out_at
         * - damaged: pending only + exclude moved_out_at
         */
        $defectSub = DB::table('product_defect_items')
            ->whereNull('moved_out_at')
            ->selectRaw($isAllBranchMode
                ? 'product_id, SUM(quantity) AS defect_qty'
                : 'product_id, branch_id, warehouse_id, SUM(quantity) AS defect_qty'
            )
            ->groupBy($isAllBranchMode ? ['product_id'] : ['product_id', 'branch_id', 'warehouse_id']);

        $damagedSub = DB::table('product_damaged_items')
            ->where('resolution_status', 'pending')
            ->whereNull('moved_out_at')
            ->selectRaw($isAllBranchMode
                ? 'product_id, SUM(quantity) AS damaged_qty'
                : 'product_id, branch_id, warehouse_id, SUM(quantity) AS damaged_qty'
            )
            ->groupBy($isAllBranchMode ? ['product_id'] : ['product_id', 'branch_id', 'warehouse_id']);

        $q = $model->newQuery()->from('stocks');

        // ====== ALL BRANCH MODE ======
        if ($isAllBranchMode) {
            $q->select([
                'stocks.product_id',
                DB::raw('NULL as branch_id'),
                DB::raw('NULL as warehouse_id'),
                DB::raw('1 as is_all_branch_mode'),

                DB::raw('MAX(products.product_code) as product_code'),
                DB::raw('MAX(products.product_name) as product_name'),
                DB::raw('NULL as warehouse_name'),

                DB::raw('SUM(stocks.qty_available) as total_qty'),
                DB::raw('COALESCE(defects.defect_qty, 0) as defect_qty'),
                DB::raw('COALESCE(damaged.damaged_qty, 0) as damaged_qty'),

                // ✅ FIX: GREATEST syntax (no trailing comma)
                DB::raw('
                    GREATEST(
                        SUM(stocks.qty_available)
                        - COALESCE(defects.defect_qty,0)
                        - COALESCE(damaged.damaged_qty,0),
                        0
                    ) as good_qty
                '),
            ])
                ->leftJoin('products', 'products.id', '=', 'stocks.product_id')
                ->leftJoinSub($defectSub, 'defects', fn ($join) => $join->on('defects.product_id', '=', 'stocks.product_id'))
                ->leftJoinSub($damagedSub, 'damaged', fn ($join) => $join->on('damaged.product_id', '=', 'stocks.product_id'))
                ->groupBy('stocks.product_id');

            // filter gudang tetap boleh (meski tidak tampil nama gudang)
            if (!empty($warehouseId)) {
                $q->where('stocks.warehouse_id', $warehouseId);
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

        // ====== SPECIFIC BRANCH MODE ======
        $q->select([
            'stocks.product_id',
            'stocks.branch_id',
            'stocks.warehouse_id',
            DB::raw('0 as is_all_branch_mode'),

            DB::raw('MAX(products.product_code) as product_code'),
            DB::raw('MAX(products.product_name) as product_name'),
            DB::raw('MAX(warehouses.warehouse_name) as warehouse_name'),

            DB::raw('SUM(stocks.qty_available) as total_qty'),
            DB::raw('COALESCE(defects.defect_qty, 0) as defect_qty'),
            DB::raw('COALESCE(damaged.damaged_qty, 0) as damaged_qty'),

            // ✅ FIX: GREATEST syntax (no trailing comma)
            DB::raw('
                GREATEST(
                    SUM(stocks.qty_available)
                    - COALESCE(defects.defect_qty,0)
                    - COALESCE(damaged.damaged_qty,0),
                    0
                ) as good_qty
            '),
        ])
            ->leftJoin('products', 'products.id', '=', 'stocks.product_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->leftJoinSub($defectSub, 'defects', function ($join) {
                $join->on('defects.product_id', '=', 'stocks.product_id')
                    ->on('defects.branch_id', '=', 'stocks.branch_id')
                    ->on('defects.warehouse_id', '=', 'stocks.warehouse_id');
            })
            ->leftJoinSub($damagedSub, 'damaged', function ($join) {
                $join->on('damaged.product_id', '=', 'stocks.product_id')
                    ->on('damaged.branch_id', '=', 'stocks.branch_id')
                    ->on('damaged.warehouse_id', '=', 'stocks.warehouse_id');
            })
            ->groupBy('stocks.product_id', 'stocks.branch_id', 'stocks.warehouse_id');

        if (!empty($warehouseId)) {
            $q->where('stocks.warehouse_id', $warehouseId);
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

    public function html()
    {
        $isAllBranchMode = (session('active_branch') === 'all');

        return $this->builder()
            ->setTableId('stocks-table')
            ->columns($this->getColumns($isAllBranchMode))
            ->minifiedAjax()
            ->orderBy(1, 'asc')
            ->parameters([
                'responsive' => true,
                'autoWidth' => false,
                'processing' => true,
                'serverSide' => true,
                'pageLength' => 50,
                'lengthMenu' => [25, 50, 100],
                'searching' => false,
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
        }

        return $cols;
    }

    protected function filename(): string
    {
        return 'Stocks_' . date('YmdHis');
    }
}
