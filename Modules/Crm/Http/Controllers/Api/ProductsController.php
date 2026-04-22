<?php

namespace Modules\Crm\Http\Controllers\Api;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
