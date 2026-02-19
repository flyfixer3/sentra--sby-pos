<?php

namespace Modules\Adjustment\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Support\BranchContext;

class AdjustmentPickController extends Controller
{
    /**
     * Endpoint modal "Pick Units"
     * - For DEFECT: ambil dari product_defect_items
     * - For DAMAGED: ambil dari product_damaged_items
     *
     * Query params:
     * - product_id (required)
     * - warehouse_id (optional, 0/empty = all)
     * - rack_id (optional, 0/empty = all)
     * - condition (required) : defect|damaged
     */
    public function pickUnits(Request $request)
    {
        try {
            $branchId   = (int) BranchContext::id();
            if ($branchId <= 0) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Branch context is not selected.',
                ], 422);
            }

            $productId  = (int) $request->query('product_id', 0);
            $warehouseId= (int) $request->query('warehouse_id', 0);
            $rackId     = (int) $request->query('rack_id', 0);
            $condition  = strtolower(trim((string) $request->query('condition', '')));

            if ($productId <= 0) {
                return response()->json(['ok' => false, 'message' => 'product_id is required'], 422);
            }
            if (!in_array($condition, ['defect', 'damaged'], true)) {
                return response()->json(['ok' => false, 'message' => 'condition must be defect|damaged'], 422);
            }

            // --- Load racks options (untuk dropdown "from rack" & "to rack")
            $racksQ = DB::table('racks')
                ->join('warehouses', 'warehouses.id', '=', 'racks.warehouse_id')
                ->where('warehouses.branch_id', $branchId)
                ->select([
                    'racks.id',
                    'racks.warehouse_id',
                    'racks.code',
                    'racks.name',
                ])
                ->orderBy('racks.warehouse_id')
                ->orderBy('racks.code')
                ->orderBy('racks.name');

            if ($warehouseId > 0) {
                // Optional: kalau warehouse dipilih, rack list bisa difilter lebih cepat
                $racksQ->where('racks.warehouse_id', $warehouseId);
            }

            $racks = $racksQ->get();

            // group racks by warehouse_id (mirip yang kamu pakai di SaleDelivery confirm)
            $racksByWarehouse = [];
            foreach ($racks as $r) {
                $wid = (int) $r->warehouse_id;
                $racksByWarehouse[$wid][] = [
                    'id'   => (int) $r->id,
                    'code' => (string) ($r->code ?? ''),
                    'name' => (string) ($r->name ?? ''),
                ];
            }

            // --- Units list
            $units = [];

            if ($condition === 'defect') {
                $q = DB::table('product_defect_items')
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->whereNull('moved_out_at')
                    ->select([
                        'id',
                        'warehouse_id',
                        'rack_id',
                        'defect_type',
                        'description',
                        'created_at',
                    ])
                    ->orderBy('id');

                if ($warehouseId > 0) $q->where('warehouse_id', $warehouseId);
                if ($rackId > 0) $q->where('rack_id', $rackId);

                $rows = $q->get();

                foreach ($rows as $r) {
                    $units[] = [
                        'id' => (int) $r->id,
                        'warehouse_id' => (int) ($r->warehouse_id ?? 0),
                        'rack_id' => (int) ($r->rack_id ?? 0),
                        'meta' => [
                            'type' => 'defect',
                            'defect_type' => (string) ($r->defect_type ?? ''),
                            'description' => (string) ($r->description ?? ''),
                        ],
                    ];
                }
            }

            if ($condition === 'damaged') {
                $q = DB::table('product_damaged_items')
                    ->where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->where('damage_type', 'damaged')
                    ->where('resolution_status', 'pending')
                    ->whereNull('moved_out_at')
                    ->select([
                        'id',
                        'warehouse_id',
                        'rack_id',
                        'reason',
                        'created_at',
                    ])
                    ->orderBy('id');

                if ($warehouseId > 0) $q->where('warehouse_id', $warehouseId);
                if ($rackId > 0) $q->where('rack_id', $rackId);

                $rows = $q->get();

                foreach ($rows as $r) {
                    $units[] = [
                        'id' => (int) $r->id,
                        'warehouse_id' => (int) ($r->warehouse_id ?? 0),
                        'rack_id' => (int) ($r->rack_id ?? 0),
                        'meta' => [
                            'type' => 'damaged',
                            'reason' => (string) ($r->reason ?? ''),
                        ],
                    ];
                }
            }

            return response()->json([
                'ok' => true,
                'data' => [
                    'units' => $units,
                    'racksByWarehouse' => $racksByWarehouse,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
