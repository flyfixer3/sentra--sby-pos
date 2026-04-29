<?php

namespace Modules\Inventory\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;

class StockOpnameService
{
    public function resolveWarehouseForBranch(int $branchId, ?int $warehouseId = null): Warehouse
    {
        $query = Warehouse::query()->where('branch_id', $branchId);

        if ($warehouseId !== null && $warehouseId > 0) {
            return $query->where('id', $warehouseId)->firstOrFail();
        }

        return $query
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->firstOrFail();
    }

    public function resolveDefaultRackSnapshot(int $warehouseId): array
    {
        $rack = DB::table('racks')
            ->where('warehouse_id', $warehouseId)
            ->orderBy('id')
            ->first(['id', 'code', 'name']);

        if (!$rack) {
            return [
                'rack_id' => null,
                'rack_code' => null,
                'rack_name' => null,
            ];
        }

        return [
            'rack_id' => (int) $rack->id,
            'rack_code' => (string) ($rack->code ?? ''),
            'rack_name' => (string) ($rack->name ?? ''),
        ];
    }

    public function buildDraftRows(int $branchId, bool $includeZeroStock = false): Collection
    {
        $products = Product::withoutGlobalScopes()
            ->where('item_type', 'glass')
            ->orderBy('product_code')
            ->get(['id', 'product_code', 'product_name']);

        $stockRows = DB::table('stock_racks as sr')
            ->leftJoin('racks as r', 'r.id', '=', 'sr.rack_id')
            ->where('sr.branch_id', $branchId)
            ->select([
                'sr.product_id',
                'sr.rack_id',
                'sr.qty_total',
                'r.code as rack_code',
                'r.name as rack_name',
            ])
            ->orderBy('sr.product_id')
            ->orderByDesc('sr.qty_total')
            ->orderBy('sr.rack_id')
            ->get();

        $stockMap = [];
        foreach ($stockRows as $row) {
            $productId = (int) $row->product_id;
            if (!isset($stockMap[$productId])) {
                $stockMap[$productId] = [
                    'system_qty' => 0,
                    'rack_id' => $row->rack_id ? (int) $row->rack_id : null,
                    'rack_code' => (string) ($row->rack_code ?? ''),
                    'rack_name' => (string) ($row->rack_name ?? ''),
                ];
            }

            $stockMap[$productId]['system_qty'] += (int) ($row->qty_total ?? 0);
        }

        return $products
            ->filter(function ($product) use ($stockMap, $includeZeroStock) {
                if ($includeZeroStock) {
                    return true;
                }

                return ((int) ($stockMap[$product->id]['system_qty'] ?? 0)) > 0;
            })
            ->map(function ($product) use ($stockMap) {
                $stock = $stockMap[$product->id] ?? [
                    'system_qty' => 0,
                    'rack_id' => null,
                    'rack_code' => '',
                    'rack_name' => '',
                ];

                return [
                    'product_id' => (int) $product->id,
                    'product_code_snapshot' => (string) $product->product_code,
                    'product_name_snapshot' => (string) $product->product_name,
                    'rack_id' => $stock['rack_id'],
                    'rack_code_snapshot' => (string) ($stock['rack_code'] ?? ''),
                    'rack_name_snapshot' => (string) ($stock['rack_name'] ?? ''),
                    'system_qty' => (int) ($stock['system_qty'] ?? 0),
                ];
            })
            ->values();
    }

    public function buildSingleRow(int $branchId, int $productId): ?array
    {
        $product = Product::withoutGlobalScopes()
            ->where('id', $productId)
            ->where('item_type', 'glass')
            ->first(['id', 'product_code', 'product_name']);

        if (!$product) {
            return null;
        }

        $stockRows = DB::table('stock_racks as sr')
            ->leftJoin('racks as r', 'r.id', '=', 'sr.rack_id')
            ->where('sr.branch_id', $branchId)
            ->where('sr.product_id', $productId)
            ->orderByDesc('sr.qty_total')
            ->orderBy('sr.rack_id')
            ->get([
                'sr.rack_id',
                'sr.qty_total',
                'r.code as rack_code',
                'r.name as rack_name',
            ]);

        $systemQty = 0;
        $rackId = null;
        $rackCode = '';
        $rackName = '';

        foreach ($stockRows as $index => $row) {
            $systemQty += (int) ($row->qty_total ?? 0);
            if ($index === 0) {
                $rackId = $row->rack_id ? (int) $row->rack_id : null;
                $rackCode = (string) ($row->rack_code ?? '');
                $rackName = (string) ($row->rack_name ?? '');
            }
        }

        return [
            'product_id' => (int) $product->id,
            'product_code_snapshot' => (string) $product->product_code,
            'product_name_snapshot' => (string) $product->product_name,
            'rack_id' => $rackId,
            'rack_code_snapshot' => $rackCode,
            'rack_name_snapshot' => $rackName,
            'system_qty' => $systemQty,
        ];
    }
}
