<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductAvailability
{
    public static function activeBranchId(): ?int
    {
        $activeBranch = session('active_branch');

        if ($activeBranch === 'all' || $activeBranch === null || $activeBranch === '') {
            return null;
        }

        return is_numeric($activeBranch) ? (int) $activeBranch : null;
    }

    public static function normalizeUnit(?string $unit): string
    {
        $unit = trim((string) $unit);

        return $unit !== '' ? $unit : 'unit';
    }

    public static function formatLabel($product, ?int $availableQty = null): string
    {
        $code = trim((string) data_get($product, 'product_code', '-'));
        $name = trim((string) data_get($product, 'product_name', '-'));
        $unit = static::normalizeUnit(data_get($product, 'product_unit'));
        $qty = $availableQty ?? (int) data_get($product, 'available_stock_qty', 0);

        return $code . ' | ' . $name . ' | ' . number_format(max(0, (int) $qty)) . ' ' . $unit . ' avail';
    }

    public static function snapshotsForProducts($productIds, ?int $branchId = null, ?int $warehouseId = null): Collection
    {
        $productIds = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $branchId = $branchId ?? static::activeBranchId();
        if (!$branchId) {
            return collect();
        }

        $warehouseId = $warehouseId && $warehouseId > 0 ? (int) $warehouseId : null;
        $resolvedStockBranchExpr = 'COALESCE(stocks.branch_id, warehouses.branch_id)';

        $stockAgg = DB::table('stocks')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->whereIn('stocks.product_id', $productIds->all())
            ->whereRaw($resolvedStockBranchExpr . ' = ?', [(int) $branchId])
            ->when($warehouseId, fn ($query) => $query->where('stocks.warehouse_id', $warehouseId))
            ->select([
                'stocks.product_id',
                DB::raw('COALESCE(SUM(stocks.qty_total), 0) as total_qty'),
                DB::raw('COALESCE(SUM(stocks.qty_reserved), 0) as reserved_qty'),
                DB::raw('COALESCE(SUM(stocks.qty_incoming), 0) as incoming_qty'),
            ])
            ->groupBy('stocks.product_id');

        $defectAgg = DB::table('product_defect_items')
            ->whereIn('product_id', $productIds->all())
            ->where('branch_id', (int) $branchId)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->whereNull('moved_out_at')
            ->selectRaw('product_id, SUM(quantity) AS defect_qty')
            ->groupBy('product_id');

        $damagedAgg = DB::table('product_damaged_items')
            ->whereIn('product_id', $productIds->all())
            ->where('branch_id', (int) $branchId)
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))
            ->where('resolution_status', 'pending')
            ->whereNull('moved_out_at')
            ->selectRaw('product_id, SUM(quantity) AS damaged_qty')
            ->groupBy('product_id');

        return DB::query()
            ->fromSub($stockAgg, 'stock_agg')
            ->leftJoinSub($defectAgg, 'defects', 'defects.product_id', '=', 'stock_agg.product_id')
            ->leftJoinSub($damagedAgg, 'damaged', 'damaged.product_id', '=', 'stock_agg.product_id')
            ->select([
                'stock_agg.product_id',
                DB::raw('COALESCE(stock_agg.total_qty, 0) as total_qty'),
                DB::raw('COALESCE(stock_agg.reserved_qty, 0) as reserved_qty'),
                DB::raw('COALESCE(stock_agg.incoming_qty, 0) as incoming_qty'),
                DB::raw('COALESCE(defects.defect_qty, 0) as defect_qty'),
                DB::raw('COALESCE(damaged.damaged_qty, 0) as damaged_qty'),
                DB::raw('
                    GREATEST(
                        COALESCE(stock_agg.total_qty, 0)
                        - COALESCE(defects.defect_qty, 0)
                        - COALESCE(damaged.damaged_qty, 0),
                        0
                    ) as good_qty
                '),
                DB::raw('
                    GREATEST(
                        (
                            GREATEST(
                                COALESCE(stock_agg.total_qty, 0) - COALESCE(damaged.damaged_qty, 0),
                                0
                            )
                        ) - COALESCE(stock_agg.reserved_qty, 0),
                        0
                    ) as available_qty
                '),
            ])
            ->get()
            ->keyBy(fn ($row) => (int) $row->product_id);
    }

    public static function applyLabels($products, ?int $branchId = null, ?int $warehouseId = null): Collection
    {
        $products = collect($products);
        $snapshots = static::snapshotsForProducts($products->pluck('id'), $branchId, $warehouseId);

        return $products->map(function ($product) use ($snapshots) {
            $productId = (int) data_get($product, 'id', 0);
            $snapshot = $snapshots->get($productId);
            $availableQty = max(0, (int) ($snapshot->available_qty ?? 0));
            $unit = static::normalizeUnit(data_get($product, 'product_unit'));

            if (is_array($product)) {
                $product['available_stock_qty'] = $availableQty;
                $product['available_stock_unit'] = $unit;
                $product['available_stock_label'] = static::formatLabel($product, $availableQty);
                return $product;
            }

            $product->setAttribute('available_stock_qty', $availableQty);
            $product->setAttribute('available_stock_unit', $unit);
            $product->setAttribute('available_stock_label', static::formatLabel($product, $availableQty));

            return $product;
        });
    }
}
