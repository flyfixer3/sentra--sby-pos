<?php

namespace Modules\Transfer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Warehouse;
use Modules\Transfer\Entities\TransferRequest;
use Modules\PurchaseOrder\Entities\PurchaseOrder;
use Modules\Purchase\Entities\Purchase;
use Modules\PurchaseDelivery\Entities\PurchaseDelivery;
use Modules\Adjustment\Entities\Adjustment;

class TransferQualityReportController extends Controller
{
    private function activeBranch()
    {
        return session('active_branch');
    }

    public function index(Request $request)
    {
        abort_if(Gate::denies('access_transfers'), 403);

        $active = $this->activeBranch(); // 'all' atau branch_id

        $request->validate([
            'branch_id'    => 'nullable',
            'warehouse_id' => 'nullable|integer',
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date',
            'q'            => 'nullable|string|max:255',
            'type'         => 'nullable|in:all,defect,damaged',
        ]);

        $type = $request->get('type', 'all');

        // branch filter
        $branchId = null;
        if ($active === 'all') {
            if ($request->filled('branch_id') && $request->branch_id !== 'all') {
                $branchId = (int) $request->branch_id;
            }
        } else {
            $branchId = (int) $active;
        }

        $warehouseId = $request->filled('warehouse_id') ? (int) $request->warehouse_id : null;
        $dateFrom    = $request->filled('date_from') ? $request->date_from : null;
        $dateTo      = $request->filled('date_to') ? $request->date_to : null;
        $q           = trim((string) $request->get('q', ''));

        // dropdown branches
        $branches = collect();
        if ($active === 'all') {
            $branches = Branch::query()->orderBy('name')->get();
        }

        // dropdown warehouses
        $warehouses = Warehouse::query()
            ->when($branchId !== null, function ($qr) use ($branchId) {
                $qr->where('branch_id', $branchId);
            })
            ->orderBy('warehouse_name')
            ->get();

        // Reference Types (Polymorphic)
        $transferClass          = TransferRequest::class;
        $purchaseOrderClass     = PurchaseOrder::class;
        $purchaseClass          = Purchase::class;
        $purchaseDeliveryClass  = PurchaseDelivery::class;
        $adjustmentClass        = Adjustment::class;

        // =========================
        // DEFECT QUERY (WITH MULTI REFERENCE)
        // =========================
        $defectsQuery = DB::table('product_defect_items as d')
            ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'd.warehouse_id')
            ->leftJoin('branches as b', 'b.id', '=', 'd.branch_id')

            // Transfer
            ->leftJoin('transfer_requests as tr', function ($join) use ($transferClass) {
                $join->on('tr.id', '=', 'd.reference_id')
                    ->where('d.reference_type', '=', $transferClass);
            })

            // Purchase Order
            ->leftJoin('purchase_orders as po', function ($join) use ($purchaseOrderClass) {
                $join->on('po.id', '=', 'd.reference_id')
                    ->where('d.reference_type', '=', $purchaseOrderClass);
            })

            // Purchase
            ->leftJoin('purchases as pur', function ($join) use ($purchaseClass) {
                $join->on('pur.id', '=', 'd.reference_id')
                    ->where('d.reference_type', '=', $purchaseClass);
            })

            // Purchase Delivery
            ->leftJoin('purchase_deliveries as pd', function ($join) use ($purchaseDeliveryClass) {
                $join->on('pd.id', '=', 'd.reference_id')
                    ->where('d.reference_type', '=', $purchaseDeliveryClass);
            })

            // Adjustment (optional join; kita pakai label ADJ-xxxxx dari id)
            ->leftJoin('adjustments as adj', function ($join) use ($adjustmentClass) {
                $join->on('adj.id', '=', 'd.reference_id')
                    ->where('d.reference_type', '=', $adjustmentClass);
            })

            ->select([
                'd.id',
                'd.created_at',
                'd.branch_id',
                'b.name as branch_name',
                'd.warehouse_id',
                'w.warehouse_name',
                'd.product_id',
                'p.product_name as product_name',
                'd.quantity',
                'd.defect_type',
                'd.description',
                'd.reference_id',
                'd.reference_type',

                'tr.reference as transfer_reference',
                'po.reference as po_reference',
                'pur.reference as purchase_reference',

                // ✅ reference label final (yang tampil di UI)
                DB::raw("
                    CASE
                        WHEN d.reference_type = " . DB::getPdo()->quote($transferClass) . " THEN tr.reference
                        WHEN d.reference_type = " . DB::getPdo()->quote($purchaseOrderClass) . " THEN po.reference
                        WHEN d.reference_type = " . DB::getPdo()->quote($purchaseClass) . " THEN pur.reference
                        WHEN d.reference_type = " . DB::getPdo()->quote($purchaseDeliveryClass) . " THEN CONCAT('PD-', LPAD(pd.id, 5, '0'))
                        WHEN d.reference_type = " . DB::getPdo()->quote($adjustmentClass) . "
                            THEN CASE
                                WHEN d.reference_id IS NOT NULL THEN CONCAT('ADJ-', LPAD(d.reference_id, 5, '0'))
                                ELSE 'ADJ'
                            END
                        ELSE NULL
                    END as reference_label
                "),

                // ✅ reference source (buat badge dan debug)
                DB::raw("
                    CASE
                        WHEN d.reference_type = " . DB::getPdo()->quote($transferClass) . " THEN 'TRANSFER'
                        WHEN d.reference_type = " . DB::getPdo()->quote($purchaseOrderClass) . " THEN 'PURCHASE_ORDER'
                        WHEN d.reference_type = " . DB::getPdo()->quote($purchaseClass) . " THEN 'PURCHASE'
                        WHEN d.reference_type = " . DB::getPdo()->quote($purchaseDeliveryClass) . " THEN 'PURCHASE_DELIVERY'
                        WHEN d.reference_type = " . DB::getPdo()->quote($adjustmentClass) . " THEN 'ADJUSTMENT'
                        ELSE 'OTHER'
                    END as reference_source
                "),
            ])
            ->when($branchId !== null, fn($qr) => $qr->where('d.branch_id', $branchId))
            ->when($warehouseId !== null, fn($qr) => $qr->where('d.warehouse_id', $warehouseId))
            ->when($dateFrom, fn($qr) => $qr->whereDate('d.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($qr) => $qr->whereDate('d.created_at', '<=', $dateTo))
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($sub) use ($q) {
                    $sub->where('p.product_name', 'like', "%{$q}%")
                        ->orWhere('d.defect_type', 'like', "%{$q}%")
                        ->orWhere('d.description', 'like', "%{$q}%")
                        ->orWhere('tr.reference', 'like', "%{$q}%")
                        ->orWhere('po.reference', 'like', "%{$q}%")
                        ->orWhere('pur.reference', 'like', "%{$q}%")
                        ->orWhereRaw("CONCAT('PD-', LPAD(pd.id, 5, '0')) like ?", ["%{$q}%"])
                        ->orWhereRaw("CONCAT('ADJ-', LPAD(d.reference_id, 5, '0')) like ?", ["%{$q}%"])
                        ->orWhereRaw("d.reference_type like ?", ["%{$q}%"]);
                });
            });

        // =========================
        // DAMAGED QUERY (WITH MULTI REFERENCE)
        // =========================
        $damagedQuery = DB::table('product_damaged_items as dm')
            ->leftJoin('products as p', 'p.id', '=', 'dm.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'dm.warehouse_id')
            ->leftJoin('branches as b', 'b.id', '=', 'dm.branch_id')

            // Transfer
            ->leftJoin('transfer_requests as tr', function ($join) use ($transferClass) {
                $join->on('tr.id', '=', 'dm.reference_id')
                    ->where('dm.reference_type', '=', $transferClass);
            })

            // PO
            ->leftJoin('purchase_orders as po', function ($join) use ($purchaseOrderClass) {
                $join->on('po.id', '=', 'dm.reference_id')
                    ->where('dm.reference_type', '=', $purchaseOrderClass);
            })

            // Purchase
            ->leftJoin('purchases as pur', function ($join) use ($purchaseClass) {
                $join->on('pur.id', '=', 'dm.reference_id')
                    ->where('dm.reference_type', '=', $purchaseClass);
            })

            // PD
            ->leftJoin('purchase_deliveries as pd', function ($join) use ($purchaseDeliveryClass) {
                $join->on('pd.id', '=', 'dm.reference_id')
                    ->where('dm.reference_type', '=', $purchaseDeliveryClass);
            })

            // Adjustment
            ->leftJoin('adjustments as adj', function ($join) use ($adjustmentClass) {
                $join->on('adj.id', '=', 'dm.reference_id')
                    ->where('dm.reference_type', '=', $adjustmentClass);
            })

            ->select([
                'dm.id',
                'dm.created_at',
                'dm.branch_id',
                'b.name as branch_name',
                'dm.warehouse_id',
                'w.warehouse_name',
                'dm.product_id',
                'p.product_name as product_name',
                'dm.quantity',
                'dm.reason',
                'dm.mutation_in_id',
                'dm.mutation_out_id',
                'dm.reference_id',
                'dm.reference_type',

                'tr.reference as transfer_reference',
                'po.reference as po_reference',
                'pur.reference as purchase_reference',

                DB::raw("
                    CASE
                        WHEN dm.reference_type = " . DB::getPdo()->quote($transferClass) . " THEN tr.reference
                        WHEN dm.reference_type = " . DB::getPdo()->quote($purchaseOrderClass) . " THEN po.reference
                        WHEN dm.reference_type = " . DB::getPdo()->quote($purchaseClass) . " THEN pur.reference
                        WHEN dm.reference_type = " . DB::getPdo()->quote($purchaseDeliveryClass) . " THEN CONCAT('PD-', LPAD(pd.id, 5, '0'))
                        WHEN dm.reference_type = " . DB::getPdo()->quote($adjustmentClass) . "
                            THEN CASE
                                WHEN dm.reference_id IS NOT NULL THEN CONCAT('ADJ-', LPAD(dm.reference_id, 5, '0'))
                                ELSE 'ADJ'
                            END
                        ELSE NULL
                    END as reference_label
                "),

                DB::raw("
                    CASE
                        WHEN dm.reference_type = " . DB::getPdo()->quote($transferClass) . " THEN 'TRANSFER'
                        WHEN dm.reference_type = " . DB::getPdo()->quote($purchaseOrderClass) . " THEN 'PURCHASE_ORDER'
                        WHEN dm.reference_type = " . DB::getPdo()->quote($purchaseClass) . " THEN 'PURCHASE'
                        WHEN dm.reference_type = " . DB::getPdo()->quote($purchaseDeliveryClass) . " THEN 'PURCHASE_DELIVERY'
                        WHEN dm.reference_type = " . DB::getPdo()->quote($adjustmentClass) . " THEN 'ADJUSTMENT'
                        ELSE 'OTHER'
                    END as reference_source
                "),
            ])
            ->when($branchId !== null, fn($qr) => $qr->where('dm.branch_id', $branchId))
            ->when($warehouseId !== null, fn($qr) => $qr->where('dm.warehouse_id', $warehouseId))
            ->when($dateFrom, fn($qr) => $qr->whereDate('dm.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($qr) => $qr->whereDate('dm.created_at', '<=', $dateTo))
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($sub) use ($q) {
                    $sub->where('p.product_name', 'like', "%{$q}%")
                        ->orWhere('dm.reason', 'like', "%{$q}%")
                        ->orWhere('tr.reference', 'like', "%{$q}%")
                        ->orWhere('po.reference', 'like', "%{$q}%")
                        ->orWhere('pur.reference', 'like', "%{$q}%")
                        ->orWhereRaw("CONCAT('PD-', LPAD(pd.id, 5, '0')) like ?", ["%{$q}%"])
                        ->orWhereRaw("CONCAT('ADJ-', LPAD(dm.reference_id, 5, '0')) like ?", ["%{$q}%"])
                        ->orWhereRaw("dm.reference_type like ?", ["%{$q}%"]);
                });
            });

        // =========================
        // EXECUTE
        // =========================
        $defects = collect();
        $damaged = collect();

        if ($type === 'all' || $type === 'defect') {
            $defects = collect($defectsQuery->orderByDesc('d.id')->limit(500)->get());
        }
        if ($type === 'all' || $type === 'damaged') {
            $damaged = collect($damagedQuery->orderByDesc('dm.id')->limit(500)->get());
        }

        $totalDefectQty  = (int) $defects->sum('quantity');
        $totalDamagedQty = (int) $damaged->sum('quantity');

        return view('transfer::quality-report.index', [
            'active'                 => $active,
            'branches'               => $branches,
            'warehouses'             => $warehouses,
            'selectedBranchId'       => $branchId,
            'selectedWarehouseId'    => $warehouseId,
            'dateFrom'               => $dateFrom,
            'dateTo'                 => $dateTo,
            'q'                      => $q,
            'type'                   => $type,
            'defects'                => $defects,
            'damaged'                => $damaged,
            'totalDefectQty'         => $totalDefectQty,
            'totalDamagedQty'        => $totalDamagedQty,

            // pass reference class names to view (biar blade bisa bikin link yang benar)
            'transferClass'          => $transferClass,
            'purchaseOrderClass'     => $purchaseOrderClass,
            'purchaseClass'          => $purchaseClass,
            'purchaseDeliveryClass'  => $purchaseDeliveryClass,
            'adjustmentClass'        => $adjustmentClass,
        ]);
    }
}
