<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Entities\Stock;
use Modules\Inventory\Entities\StockRack;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Warehouse;
use Modules\Product\Entities\Product;

class StockController extends Controller
{
    /**
     * Tampilkan daftar stok beserta rincian per-rak
     */
    public function index(Request $request)
    {
        $query = Stock::with(['product', 'branch', 'warehouse']);

        // filter berdasarkan cabang, gudang, produk
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('product')) {
            $term = '%' . $request->get('product') . '%';
            $query->whereHas('product', function ($q) use ($term) {
                $q->where('product_name', 'like', $term)
                    ->orWhere('product_code', 'like', $term);
            });
        }

        $stocks = $query->orderByDesc('id')->paginate(50);

        // preload data branch & warehouse untuk dropdown filter
        $branches = Branch::all();
        $warehouses = Warehouse::all();

        return view('inventory::stocks.index', compact('stocks', 'branches', 'warehouses'));
    }

    /**
     * Detail stok per rak berdasarkan kombinasi branch + warehouse + product
     */
    public function rackDetails($productId, $branchId, $warehouseId)
    {
        $stockRacks = StockRack::with(['rack'])
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stockRacks
        ]);
    }
}
