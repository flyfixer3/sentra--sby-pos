<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\DataTables\StocksDataTable;
use Modules\Inventory\Entities\StockRack;
use Modules\Product\Entities\Warehouse;

class StockController extends Controller
{
    public function index(Request $request, StocksDataTable $dataTable)
    {
        $warehouses = Warehouse::orderBy('warehouse_name')->get();
        $isAllBranchMode = (session('active_branch') === 'all');

        return $dataTable->render('inventory::stocks.index', compact('warehouses', 'isAllBranchMode'));
    }

    public function rackDetails($productId, $branchId, $warehouseId)
    {
        $stockRacks = StockRack::with(['rack'])
            ->where('product_id', (int) $productId)
            ->where('branch_id', (int) $branchId)
            ->where('warehouse_id', (int) $warehouseId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stockRacks
        ]);
    }

    /**
     * ✅ Quality Details:
     * type: defect | damaged
     * productId: product id
     * mode:
     * - kalau session active_branch = all => tampilkan semua branch
     * - kalau bukan all => otomatis terfilter branch itu (by HasBranchScope biasanya),
     *   tapi di sini kita explicit filter juga supaya jelas.
     */
    public function qualityDetails($type, $productId, Request $request)
    {
        $type = strtolower((string) $type);
        $productId = (int) $productId;

        if (!in_array($type, ['defect', 'damaged'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid type. Allowed: defect, damaged.'
            ], 422);
        }

        $activeBranch = session('active_branch');
        $isAllBranchMode = ($activeBranch === 'all');

        // optional filter from UI (kalau nanti kamu mau filter gudang)
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->warehouse_id : null;

        if ($type === 'defect') {
            $q = DB::table('product_defect_items as i')
                ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
                ->leftJoin('warehouses as w', 'w.id', '=', 'i.warehouse_id')
                ->where('i.product_id', $productId)
                ->select([
                    'i.id',
                    'i.branch_id',
                    'i.warehouse_id',
                    'i.quantity',
                    'i.defect_type',
                    'i.description',
                    'i.photo_path',
                    'i.reference_type',
                    'i.reference_id',
                    'i.created_at',
                    DB::raw('COALESCE(b.name, "-") as branch_name'),
                    DB::raw('COALESCE(w.warehouse_name, "-") as warehouse_name'),
                ])
                ->orderByDesc('i.id');

            if (!$isAllBranchMode && is_numeric($activeBranch)) {
                $q->where('i.branch_id', (int) $activeBranch);
            }

            if (!empty($warehouseId)) {
                $q->where('i.warehouse_id', $warehouseId);
            }

            $rows = $q->get()->map(function ($r) {
                $r->photo_url = !empty($r->photo_path) ? asset('storage/' . ltrim($r->photo_path, '/')) : null;
                return $r;
            });

            return response()->json([
                'success' => true,
                'type' => 'defect',
                'data' => $rows,
            ]);
        }

        // damaged
        $q = DB::table('product_damaged_items as i')
            ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'i.warehouse_id')
            ->where('i.product_id', $productId)
            ->select([
                'i.id',
                'i.branch_id',
                'i.warehouse_id',
                'i.quantity',
                'i.reason',
                'i.photo_path',
                'i.mutation_in_id',
                'i.mutation_out_id',
                'i.reference_type',
                'i.reference_id',
                'i.created_at',
                DB::raw('COALESCE(b.name, "-") as branch_name'),
                DB::raw('COALESCE(w.warehouse_name, "-") as warehouse_name'),
            ])
            ->orderByDesc('i.id');

        if (!$isAllBranchMode && is_numeric($activeBranch)) {
            $q->where('i.branch_id', (int) $activeBranch);
        }

        if (!empty($warehouseId)) {
            $q->where('i.warehouse_id', $warehouseId);
        }

        $rows = $q->get()->map(function ($r) {
            $r->photo_url = !empty($r->photo_path) ? asset('storage/' . ltrim($r->photo_path, '/')) : null;
            return $r;
        });

        return response()->json([
            'success' => true,
            'type' => 'damaged',
            'data' => $rows,
        ]);
    }
}
