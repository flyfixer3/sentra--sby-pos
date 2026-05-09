<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Product\Entities\Product;

class ProductsController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        $search = trim((string) $request->query('search', ''));
        $limit = min(max((int) $request->query('limit', 10), 1), 25);

        $products = Product::query()
            ->withoutGlobalScope('branch') // Products are a global catalog; stock is branch-scoped separately
            ->without('media')
            ->select('id', 'product_code', 'product_name', 'product_price', 'product_unit')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('product_code', 'like', '%' . $search . '%')
                        ->orWhere('product_name', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('product_code')
            ->limit($limit)
            ->get();

        $snapshots = $this->stockSnapshots($products->pluck('id')->map(fn ($id) => (int) $id)->all());

        return response()->json([
            'data' => $products->map(function (Product $product) use ($snapshots) {
                $stock = $snapshots[(int) $product->id] ?? [
                    'total_qty' => 0,
                    'reserved_qty' => 0,
                    'incoming_qty' => 0,
                    'available_qty' => 0,
                    'stock_status' => 'tidak_tersedia',
                ];

                return array_merge([
                    'id' => (int) $product->id,
                    'product_code' => $product->product_code,
                    'product_name' => $product->product_name,
                    'product_price' => (int) ($product->product_price ?? 0),
                    'product_unit' => $product->product_unit,
                ], $stock);
            })->values(),
        ]);
    }

    /**
     * GET /api/crm/products/{id}/branch-stock?branch_id=X
     *
     * Returns detailed per-rack stock breakdown for a single product in a specific branch.
     * Includes qty_good, qty_defect, qty_damaged per rack location. Read-only.
     */
    public function branchStock(Request $request, int $id)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        $branchId = (int) $request->query('branch_id', 0);
        if ($branchId <= 0) {
            return response()->json(['error' => 'Parameter branch_id diperlukan.'], 422);
        }

        // Verify the user is allowed to access this branch
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $allowedIds = $user->allAvailableBranches()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!in_array($branchId, $allowedIds, true)) {
            abort(403, 'Akses cabang tidak diizinkan.');
        }

        // Fetch the product (global catalog)
        $product = Product::withoutGlobalScope('branch')
            ->without('media')
            ->select('id', 'product_code', 'product_name', 'product_price', 'product_unit')
            ->findOrFail($id);

        $branch = \Modules\Branch\Entities\Branch::find($branchId);

        // Aggregate stock totals for this product + branch
        $stockRow = DB::table('stocks')
            ->where('product_id', $id)
            ->where('branch_id', $branchId)
            ->selectRaw('
                COALESCE(SUM(qty_total),    0) as total_qty,
                COALESCE(SUM(qty_reserved), 0) as reserved_qty,
                COALESCE(SUM(qty_incoming), 0) as incoming_qty
            ')
            ->first();

        $totalQty    = $stockRow ? (int) $stockRow->total_qty    : 0;
        $reservedQty = $stockRow ? (int) $stockRow->reserved_qty : 0;
        $incomingQty = $stockRow ? (int) $stockRow->incoming_qty : 0;
        $availableQty = max($totalQty - $reservedQty, 0);

        // Per-rack breakdown with condition details (qty_good / defect / damaged)
        $racks = DB::table('stock_racks')
            ->join('racks', 'racks.id', '=', 'stock_racks.rack_id')
            ->leftJoin('warehouses', 'warehouses.id', '=', 'stock_racks.warehouse_id')
            ->where('stock_racks.product_id', $id)
            ->where('stock_racks.branch_id', $branchId)
            ->where(function ($q) {
                // Only return racks that actually have some quantity
                $q->where('stock_racks.qty_total', '>', 0)
                  ->orWhere('stock_racks.qty_good',    '>', 0)
                  ->orWhere('stock_racks.qty_defect',  '>', 0)
                  ->orWhere('stock_racks.qty_damaged',  '>', 0);
            })
            ->select(
                'stock_racks.rack_id',
                'racks.code as rack_code',
                'racks.name as rack_name',
                'stock_racks.warehouse_id',
                'warehouses.warehouse_name',
                'stock_racks.qty_total',
                'stock_racks.qty_good',
                'stock_racks.qty_defect',
                'stock_racks.qty_damaged',
            )
            ->orderBy('racks.code')
            ->get()
            ->map(fn ($r) => [
                'rack_id'        => (int) $r->rack_id,
                'rack_code'      => $r->rack_code,
                'rack_name'      => $r->rack_name,
                'warehouse_name' => $r->warehouse_name,
                'qty_total'      => (int) $r->qty_total,
                'qty_good'       => (int) $r->qty_good,
                'qty_defect'     => (int) $r->qty_defect,
                'qty_damaged'    => (int) $r->qty_damaged,
            ]);

        return response()->json([
            'product_id'    => (int) $product->id,
            'product_code'  => $product->product_code  ?? '',
            'product_name'  => $product->product_name  ?? '',
            'product_price' => (int) ($product->product_price ?? 0),
            'product_unit'  => $product->product_unit,
            'branch_id'     => $branchId,
            'branch_name'   => $branch?->name ?? "Branch #{$branchId}",
            'total_qty'     => $totalQty,
            'reserved_qty'  => $reservedQty,
            'incoming_qty'  => $incomingQty,
            'available_qty' => $availableQty,
            'racks'         => $racks->values(),
        ]);
    }

    /**
     * GET /api/crm/products/all-branches?search=...
     *
     * Read-only stock lookup across every branch the authenticated user can access.
     * Source of truth remains the POS/inventory system — this endpoint only reads.
     * Minimum 2-character search enforced to prevent full-catalog dumps.
     */
    public function allBranches(Request $request)
    {
        abort_if(Gate::denies('show_crm_leads'), 403);

        $search = trim((string) $request->query('search', ''));
        if (mb_strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $limit = min(max((int) $request->query('limit', 8), 1), 20);

        // 1. Search the global product catalog (no branch scope — products are shared)
        $products = Product::query()
            ->withoutGlobalScope('branch')
            ->without('media')
            ->select('id', 'product_code', 'product_name', 'product_price', 'product_unit')
            ->where(function ($q) use ($search) {
                $q->where('product_code', 'like', '%' . $search . '%')
                  ->orWhere('product_name', 'like', '%' . $search . '%');
            })
            ->orderBy('product_code')
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            return response()->json(['data' => []]);
        }

        // 2. Resolve branches the current user is allowed to see
        /** @var \App\Models\User $user */
        $user       = Auth::user();
        $allowedBranches = $user->allAvailableBranches();
        $branchIds  = $allowedBranches->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $branchMap  = $allowedBranches->keyBy('id');

        if (empty($branchIds)) {
            return response()->json(['data' => []]);
        }

        $productIds = $products->pluck('id')->map(fn ($id) => (int) $id)->all();

        // 3. Single aggregated stock query for all products × branches
        $stockIndex = [];
        DB::table('stocks')
            ->whereIn('product_id', $productIds)
            ->whereIn('branch_id', $branchIds)
            ->select(
                'product_id',
                'branch_id',
                DB::raw('COALESCE(SUM(qty_total),    0) as total_qty'),
                DB::raw('COALESCE(SUM(qty_reserved), 0) as reserved_qty'),
                DB::raw('COALESCE(SUM(qty_incoming), 0) as incoming_qty'),
            )
            ->groupBy('product_id', 'branch_id')
            ->get()
            ->each(function ($row) use (&$stockIndex) {
                $total     = (int) $row->total_qty;
                $reserved  = (int) $row->reserved_qty;
                $incoming  = (int) $row->incoming_qty;
                $available = max($total - $reserved, 0);
                $stockIndex["{$row->product_id}_{$row->branch_id}"] = [
                    'total'     => $total,
                    'reserved'  => $reserved,
                    'incoming'  => $incoming,
                    'available' => $available,
                ];
            });

        // 4. Rack locations — only where physical stock actually exists (qty_good > 0)
        $rackMap = [];
        DB::table('stock_racks')
            ->join('racks', 'racks.id', '=', 'stock_racks.rack_id')
            ->whereIn('stock_racks.product_id', $productIds)
            ->whereIn('stock_racks.branch_id', $branchIds)
            ->where('stock_racks.qty_good', '>', 0)
            ->select(
                'stock_racks.product_id',
                'stock_racks.branch_id',
                DB::raw('GROUP_CONCAT(DISTINCT racks.code ORDER BY racks.code SEPARATOR ", ") as rack_codes'),
            )
            ->groupBy('stock_racks.product_id', 'stock_racks.branch_id')
            ->get()
            ->each(function ($row) use (&$rackMap) {
                $rackMap["{$row->product_id}_{$row->branch_id}"] = (string) $row->rack_codes;
            });

        // 5. Compose response — one entry per product with per-branch breakdown
        $data = $products->map(function (Product $product) use ($branchIds, $branchMap, $stockIndex, $rackMap) {
            $pid = (int) $product->id;

            $branches = array_map(function (int $bid) use ($pid, $branchMap, $stockIndex, $rackMap) {
                $key   = "{$pid}_{$bid}";
                $stock = $stockIndex[$key] ?? ['total' => 0, 'reserved' => 0, 'incoming' => 0, 'available' => 0];

                $status = match (true) {
                    $stock['available'] > 0 => 'ready',
                    $stock['incoming']  > 0 => 'incoming',
                    default                 => 'out_of_stock',
                };

                /** @var \Modules\Branch\Entities\Branch|null $branch */
                $branch = $branchMap->get($bid);

                return [
                    'branch_id'     => $bid,
                    'branch_name'   => $branch?->name ?? "Branch #{$bid}",
                    'total_qty'     => $stock['total'],
                    'reserved_qty'  => $stock['reserved'],
                    'available_qty' => $stock['available'],
                    'incoming_qty'  => $stock['incoming'],
                    'rack_location' => $rackMap[$key] ?? null,
                    'status'        => $status,
                ];
            }, $branchIds);

            // Sort: ready (desc qty) → incoming → out_of_stock
            usort($branches, function ($a, $b) {
                static $order = ['ready' => 0, 'incoming' => 1, 'out_of_stock' => 2];
                $cmp = ($order[$a['status']] ?? 3) <=> ($order[$b['status']] ?? 3);
                return $cmp !== 0 ? $cmp : $b['available_qty'] <=> $a['available_qty'];
            });

            return [
                'id'            => $pid,
                'product_code'  => $product->product_code ?? '',
                'product_name'  => $product->product_name ?? '',
                'product_price' => (int) ($product->product_price ?? 0),
                'product_unit'  => $product->product_unit,
                'branches'      => array_values($branches),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    protected function stockSnapshots(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $branchId = BranchContext::id();
        if ($branchId === null) {
            return [];
        }

        return DB::table('stocks')
            ->where('branch_id', (int) $branchId)
            ->whereIn('product_id', $productIds)
            ->select(
                'product_id',
                DB::raw('COALESCE(SUM(qty_total),0) as total_qty'),
                DB::raw('COALESCE(SUM(qty_reserved),0) as reserved_qty'),
                DB::raw('COALESCE(SUM(qty_incoming),0) as incoming_qty')
            )
            ->groupBy('product_id')
            ->get()
            ->mapWithKeys(function ($row) {
                $total = (int) ($row->total_qty ?? 0);
                $reserved = (int) ($row->reserved_qty ?? 0);
                $incoming = (int) ($row->incoming_qty ?? 0);
                $available = max($total - $reserved, 0);

                return [
                    (int) $row->product_id => [
                        'total_qty' => $total,
                        'reserved_qty' => $reserved,
                        'incoming_qty' => $incoming,
                        'available_qty' => $available,
                        'stock_status' => $available > 0
                            ? 'tersedia'
                            : ($incoming > 0 ? 'perlu_order' : 'tidak_tersedia'),
                    ],
                ];
            })
            ->all();
    }
}
