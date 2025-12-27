<?php

namespace Modules\Transfer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Warehouse;
use Modules\Transfer\Entities\TransferRequest;

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

        $transferClass = TransferRequest::class;

        // =========================
        // DEFECT QUERY
        // =========================
        $defectsQuery = DB::table('product_defect_items as d')
            ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'd.warehouse_id')
            ->leftJoin('branches as b', 'b.id', '=', 'd.branch_id')
            ->leftJoin('transfer_requests as tr', 'tr.id', '=', 'd.reference_id')
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
                        ->orWhere('tr.reference', 'like', "%{$q}%");
                });
            });

        // =========================
        // DAMAGED QUERY (FIXED)
        // =========================
        $damagedQuery = DB::table('product_damaged_items as dm')
            ->leftJoin('products as p', 'p.id', '=', 'dm.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'dm.warehouse_id')
            ->leftJoin('branches as b', 'b.id', '=', 'dm.branch_id')
            ->leftJoin('transfer_requests as tr', 'tr.id', '=', 'dm.reference_id')
            ->select([
                'dm.id',
                'dm.created_at',
                'dm.branch_id',
                'b.name as branch_name',
                'dm.warehouse_id',
                'w.warehouse_name',
                'dm.product_id',
                'p.product_name as product_name', // ✅ FIX
                'dm.quantity',
                'dm.reason',
                'dm.mutation_in_id',
                'dm.mutation_out_id',
                'dm.reference_id',
                'dm.reference_type',
                'tr.reference as transfer_reference',
            ])
            ->when($branchId !== null, fn($qr) => $qr->where('dm.branch_id', $branchId))
            ->when($warehouseId !== null, fn($qr) => $qr->where('dm.warehouse_id', $warehouseId))
            ->when($dateFrom, fn($qr) => $qr->whereDate('dm.created_at', '>=', $dateFrom))
            ->when($dateTo, fn($qr) => $qr->whereDate('dm.created_at', '<=', $dateTo))
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($sub) use ($q) {
                    $sub->where('p.product_name', 'like', "%{$q}%") // ✅ FIX
                        ->orWhere('dm.reason', 'like', "%{$q}%")
                        ->orWhere('tr.reference', 'like', "%{$q}%");
                });
            });

        // =========================
        // EKSEKUSI
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
            'active'              => $active,
            'branches'            => $branches,
            'warehouses'          => $warehouses,
            'selectedBranchId'    => $branchId,
            'selectedWarehouseId' => $warehouseId,
            'dateFrom'            => $dateFrom,
            'dateTo'              => $dateTo,
            'q'                   => $q,
            'type'                => $type,
            'defects'             => $defects,
            'damaged'             => $damaged,
            'totalDefectQty'      => $totalDefectQty,
            'totalDamagedQty'     => $totalDamagedQty,
            'transferClass'       => $transferClass,
        ]);
    }
}
