<?php

namespace Modules\Inventory\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use Modules\Inventory\DataTables\StocksDataTable;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Warehouse;

class StockController extends Controller
{
    public function index(Request $request, StocksDataTable $dataTable)
    {
        $isAllBranchMode = (session('active_branch') === 'all');

        $branches = collect();
        if ($isAllBranchMode) {
            $branches = Branch::query()->orderBy('name')->get();
        }

        // NOTE: warehouses tidak dipakai lagi di filter utama.
        // warehouses justru dipakai di modal (via endpoint detailOptions & qualityOptions).
        return $dataTable->render('inventory::stocks.index', compact('isAllBranchMode', 'branches'));
    }

    /**
     * Dropdown options untuk Stock Detail modal:
     * - warehouses by branch scope
     * - racks by branch scope (+ optional warehouse_id)
     */
    public function detailOptions(Request $request)
    {
        $productId = (int) $request->get('product_id');
        $branchId  = (int) $request->get('branch_id');

        if ($productId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid product_id'], 422);
        }

        $activeBranch = session('active_branch');
        $isAllBranchMode = ($activeBranch === 'all');

        // enforce branch scope
        if (!$isAllBranchMode && is_numeric($activeBranch)) {
            $branchId = (int) $activeBranch;
        }
        if ($branchId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid branch_id'], 422);
        }

        /**
         * ✅ FIX: branch legacy bisa NULL di stock_racks
         * resolve branch pakai COALESCE(sr.branch_id, w.branch_id)
         */
        $resolvedSrBranchExpr = 'COALESCE(sr.branch_id, w.branch_id)';

        // Warehouses list
        $warehouseRows = DB::table('stock_racks as sr')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->where('sr.product_id', $productId)
            ->whereRaw($resolvedSrBranchExpr . ' = ?', [$branchId])
            ->selectRaw('sr.warehouse_id, MAX(w.warehouse_name) as warehouse_name')
            ->groupBy('sr.warehouse_id')
            ->orderByRaw('MAX(w.warehouse_name) asc')
            ->get();

        $warehouses = $warehouseRows->map(function ($r) {
            return [
                'value' => (string) ($r->warehouse_id ?? ''),
                'label' => (string) ($r->warehouse_name ?? ('Warehouse #' . ($r->warehouse_id ?? 0))),
            ];
        })->values();

        // Racks list
        $rackRows = DB::table('stock_racks as sr')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->leftJoin('racks as r', 'r.id', '=', 'sr.rack_id')
            ->where('sr.product_id', $productId)
            ->whereRaw($resolvedSrBranchExpr . ' = ?', [$branchId])
            ->selectRaw('sr.rack_id, MAX(r.name) as rack_name, MAX(r.code) as rack_code')
            ->groupBy('sr.rack_id')
            ->orderByRaw('MAX(r.code) asc')
            ->get();

        $racks = $rackRows->map(function ($r) {
            $label = trim((string) ($r->rack_code ?? '')) !== ''
                ? (($r->rack_code ?? '-') . ' - ' . ($r->rack_name ?? '-'))
                : (string) ($r->rack_name ?? '-');

            return [
                'value' => (string) ($r->rack_id ?? ''),
                'label' => $label,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'warehouses' => $warehouses,
            'racks' => $racks,
        ]);
    }

    /**
     * Data untuk tabel Stock Detail modal.
     * Filter:
     * - warehouse_id (optional)
     * - rack_id (optional)
     * - condition: good|defect|damaged (optional)
     *
     * Output: rows list (warehouse_name, rack_name, condition_label, qty)
     */
    public function detailData(Request $request)
    {
        $productId = (int) $request->get('product_id');
        $branchId  = (int) $request->get('branch_id');

        $warehouseId = $request->filled('warehouse_id') ? (int) $request->get('warehouse_id') : null;
        $rackId      = $request->filled('rack_id') ? (int) $request->get('rack_id') : null;
        $condition   = $request->filled('condition') ? strtolower((string) $request->get('condition')) : null;

        if ($productId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid product_id'], 422);
        }

        $activeBranch = session('active_branch');
        $isAllBranchMode = ($activeBranch === 'all');

        if (!$isAllBranchMode && is_numeric($activeBranch)) {
            $branchId = (int) $activeBranch;
        }
        if ($branchId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid branch_id'], 422);
        }

        if (!empty($condition) && !in_array($condition, ['good', 'defect', 'damaged'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid condition'], 422);
        }

        /**
         * ✅ FIX: branch legacy bisa NULL di stock_racks
         * resolve branch pakai COALESCE(sr.branch_id, w.branch_id)
         */
        $resolvedSrBranchExpr = 'COALESCE(sr.branch_id, w.branch_id)';

        $q = DB::table('stock_racks as sr')
            ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
            ->leftJoin('racks as r', 'r.id', '=', 'sr.rack_id')
            ->where('sr.product_id', $productId)
            ->whereRaw($resolvedSrBranchExpr . ' = ?', [$branchId]);

        if (!empty($warehouseId)) $q->where('sr.warehouse_id', $warehouseId);
        if (!empty($rackId)) $q->where('sr.rack_id', $rackId);

        $base = $q->select([
            'sr.warehouse_id',
            DB::raw('COALESCE(w.warehouse_name, "-") as warehouse_name'),
            'sr.rack_id',
            DB::raw('COALESCE(r.code, "") as rack_code'),
            DB::raw('COALESCE(r.name, "-") as rack_name'),
            DB::raw('COALESCE(sr.qty_good, 0) as qty_good'),
            DB::raw('COALESCE(sr.qty_defect, 0) as qty_defect'),
            DB::raw('COALESCE(sr.qty_damaged, 0) as qty_damaged'),
        ])->get();

        $rows = [];

        foreach ($base as $r) {
            $rackLabel = trim((string) ($r->rack_code ?? '')) !== ''
                ? (($r->rack_code ?? '-') . ' - ' . ($r->rack_name ?? '-'))
                : (string) ($r->rack_name ?? '-');

            $map = [
                'good'   => (int) ($r->qty_good ?? 0),
                'defect' => (int) ($r->qty_defect ?? 0),
                'damaged'=> (int) ($r->qty_damaged ?? 0),
            ];

            foreach ($map as $cond => $qty) {
                if (!empty($condition) && $cond !== $condition) continue;
                if ($qty <= 0) continue; // kalau mau tampilkan 0 juga, bilang, nanti aku ubah.

                $rows[] = [
                    'warehouse_name'   => (string) ($r->warehouse_name ?? '-'),
                    'rack_name'        => $rackLabel,
                    'condition'        => $cond,
                    'condition_label'  => strtoupper($cond),
                    'qty'              => $qty,
                ];
            }
        }

        usort($rows, function ($a, $b) {
            $w = strcmp($a['warehouse_name'], $b['warehouse_name']);
            if ($w !== 0) return $w;
            $r = strcmp($a['rack_name'], $b['rack_name']);
            if ($r !== 0) return $r;
            return strcmp($a['condition'], $b['condition']);
        });

        return response()->json([
            'success' => true,
            'data' => array_values($rows),
        ]);
    }

    /**
     * Options untuk Quality modal:
     * - warehouses, racks yang relevan untuk product+branch
     */
    public function qualityOptions(Request $request)
    {
        $type = strtolower((string) $request->get('type'));
        $productId = (int) $request->get('product_id');
        $branchId = (int) $request->get('branch_id');

        if (!in_array($type, ['defect', 'damaged'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid type'], 422);
        }
        if ($productId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid product_id'], 422);
        }

        $activeBranch = session('active_branch');
        $isAllBranchMode = ($activeBranch === 'all');

        if (!$isAllBranchMode && is_numeric($activeBranch)) {
            $branchId = (int) $activeBranch;
        }
        if ($branchId <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid branch_id'], 422);
        }

        // warehouses & racks dari item quality table
        $table = ($type === 'defect') ? 'product_defect_items' : 'product_damaged_items';

        /**
         * ✅ FIX UTAMA:
         * Anggap "BELUM moved out" hanya jika moved_out_at:
         * - NULL, atau
         * - '', atau
         * - '0000-00-00 00:00:00'
         */
        $applyNotMovedOut = function ($q) {
            $q->where(function ($qq) {
                $qq->whereNull('i.moved_out_at')
                    ->orWhere('i.moved_out_at', '=', '')
                    ->orWhere('i.moved_out_at', '=', '0000-00-00 00:00:00');
            });
            return $q;
        };

        $baseFilter = function ($q) use ($productId, $branchId, $type, $applyNotMovedOut) {
            $q->where('i.product_id', $productId)
                ->where('i.branch_id', $branchId);

            $applyNotMovedOut($q);

            if ($type === 'damaged') {
                $q->where('i.resolution_status', 'pending');
            }

            return $q;
        };

        $whRows = DB::table($table . ' as i')
            ->leftJoin('warehouses as w', 'w.id', '=', 'i.warehouse_id')
            ->tap($baseFilter)
            ->selectRaw('i.warehouse_id, MAX(w.warehouse_name) as warehouse_name')
            ->groupBy('i.warehouse_id')
            ->orderByRaw('MAX(w.warehouse_name) asc')
            ->get();

        $warehouses = $whRows->map(function ($r) {
            return [
                'value' => (string) ($r->warehouse_id ?? ''),
                'label' => (string) ($r->warehouse_name ?? ('Warehouse #' . ($r->warehouse_id ?? 0))),
            ];
        })->values();

        $rackRows = DB::table($table . ' as i')
            ->leftJoin('racks as r', 'r.id', '=', 'i.rack_id')
            ->tap($baseFilter)
            ->selectRaw('i.rack_id, MAX(r.code) as rack_code, MAX(r.name) as rack_name')
            ->groupBy('i.rack_id')
            ->orderByRaw('MAX(r.code) asc')
            ->get();

        $racks = $rackRows->map(function ($r) {
            $label = trim((string) ($r->rack_code ?? '')) !== ''
                ? (($r->rack_code ?? '-') . ' - ' . ($r->rack_name ?? '-'))
                : (string) ($r->rack_name ?? '-');

            return [
                'value' => (string) ($r->rack_id ?? ''),
                'label' => $label,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'warehouses' => $warehouses,
            'racks' => $racks,
        ]);
    }

    /**
     * Quality Details (defect/damaged)
     * Support filter: branch_id, warehouse_id, rack_id
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

        $warehouseId = $request->filled('warehouse_id') ? (int) $request->warehouse_id : null;
        $rackId = $request->filled('rack_id') ? (int) $request->rack_id : null;

        $toPhotoUrl = function (?string $photoPath): ?string {
            $photoPath = $photoPath ? trim($photoPath) : null;
            if (empty($photoPath)) return null;

            if (preg_match('~^https?://~i', $photoPath)) return $photoPath;

            $photoPath = ltrim($photoPath, '/');

            if (str_starts_with($photoPath, 'storage/')) {
                return asset($photoPath);
            }

            return asset('storage/' . $photoPath);
        };

        /**
         * ✅ FIX UTAMA:
         * Anggap "BELUM moved out" hanya jika moved_out_at:
         * - NULL, atau
         * - '', atau
         * - '0000-00-00 00:00:00'
         */
        $applyNotMovedOutFilter = function ($q) {
            return $q->where(function ($qq) {
                $qq->whereNull('i.moved_out_at')
                    ->orWhere('i.moved_out_at', '=', '')
                    ->orWhere('i.moved_out_at', '=', '0000-00-00 00:00:00');
            });
        };

        // ======================
        // DEFECT
        // ======================
        if ($type === 'defect') {
            $q = DB::table('product_defect_items as i')
                ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
                ->leftJoin('warehouses as w', 'w.id', '=', 'i.warehouse_id')
                ->leftJoin('racks as r', 'r.id', '=', 'i.rack_id')
                ->where('i.product_id', $productId)
                ->tap($applyNotMovedOutFilter)
                ->select([
                    DB::raw('i.id as product_defect_id'),
                    'i.id',
                    'i.branch_id',
                    'i.warehouse_id',
                    'i.rack_id',
                    'i.quantity',
                    'i.defect_type',
                    'i.description',
                    'i.photo_path',
                    'i.reference_type',
                    'i.reference_id',
                    'i.created_at',
                    DB::raw('COALESCE(b.name, "-") as branch_name'),
                    DB::raw('COALESCE(w.warehouse_name, "-") as warehouse_name'),
                    DB::raw('COALESCE(r.code, "-") as rack_code'),
                    DB::raw('COALESCE(r.name, "-") as rack_name'),
                ])
                ->orderByDesc('i.id');

            if (!$isAllBranchMode && is_numeric($activeBranch)) {
                $q->where('i.branch_id', (int) $activeBranch);
            }
            if (!empty($warehouseId)) $q->where('i.warehouse_id', $warehouseId);
            if (!empty($rackId)) $q->where('i.rack_id', $rackId);

            $rows = $q->get()->map(function ($r) use ($toPhotoUrl) {
                $r->photo_url = $toPhotoUrl($r->photo_path ?? null);
                return $r;
            });

            return response()->json([
                'success' => true,
                'type' => 'defect',
                'data' => $rows,
            ]);
        }

        // ======================
        // DAMAGED
        // ======================
        $q = DB::table('product_damaged_items as i')
            ->leftJoin('branches as b', 'b.id', '=', 'i.branch_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'i.warehouse_id')
            ->leftJoin('racks as r', 'r.id', '=', 'i.rack_id')
            ->where('i.product_id', $productId)
            ->where('i.resolution_status', 'pending')
            ->tap($applyNotMovedOutFilter)
            ->select([
                DB::raw('i.id as product_damaged_id'),
                'i.id',
                'i.branch_id',
                'i.warehouse_id',
                'i.rack_id',
                'i.quantity',
                'i.damage_type',
                'i.reason',
                'i.photo_path',
                'i.cause',
                'i.responsible_user_id',
                'i.mutation_in_id',
                'i.mutation_out_id',
                'i.reference_type',
                'i.reference_id',
                'i.created_at',
                DB::raw('COALESCE(b.name, "-") as branch_name'),
                DB::raw('COALESCE(w.warehouse_name, "-") as warehouse_name'),
                DB::raw('COALESCE(r.code, "-") as rack_code'),
                DB::raw('COALESCE(r.name, "-") as rack_name'),
            ])
            ->orderByDesc('i.id');

        if (!$isAllBranchMode && is_numeric($activeBranch)) {
            $q->where('i.branch_id', (int) $activeBranch);
        }
        if (!empty($warehouseId)) $q->where('i.warehouse_id', $warehouseId);
        if (!empty($rackId)) $q->where('i.rack_id', $rackId);

        $rows = $q->get()->map(function ($r) use ($toPhotoUrl) {
            $r->photo_url = $toPhotoUrl($r->photo_path ?? null);
            return $r;
        });

        return response()->json([
            'success' => true,
            'type' => 'damaged',
            'data' => $rows,
        ]);
    }

}
