<?php

namespace Modules\Product\Http\Controllers;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class HppLedgerController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Gate::denies('view_sale_hpp'), 403);

        $user = auth()->user();
        $availableBranches = $user
            ? $user->allAvailableBranches()->values()
            : collect();

        $activeBranchId = BranchContext::id();
        $defaultBranchId = $activeBranchId ?: (int) optional($availableBranches->first())->id;

        $branchId = (int) ($request->branch_id ?: $defaultBranchId);
        abort_if($branchId <= 0, 403, 'Please select a valid branch first.');

        $allowedBranchIds = $availableBranches->pluck('id')->map(fn ($id) => (int) $id)->all();
        abort_if(!in_array($branchId, $allowedBranchIds, true), 403, 'Selected branch is not accessible.');

        $productId = (int) ($request->product_id ?: 0);
        $sourceType = trim((string) $request->source_type);
        $dateFrom = $request->date_from ? (string) $request->date_from : null;
        $dateTo = $request->date_to ? (string) $request->date_to : null;

        $rows = DB::table('product_hpps')
            ->join('products', 'products.id', '=', 'product_hpps.product_id')
            ->join('branches', 'branches.id', '=', 'product_hpps.branch_id')
            ->select([
                'product_hpps.*',
                'products.product_name',
                'products.product_code',
                'branches.name as branch_name',
            ])
            ->where('product_hpps.branch_id', $branchId)
            ->when($productId > 0, fn ($query) => $query->where('product_hpps.product_id', $productId))
            ->when($sourceType !== '', fn ($query) => $query->where('product_hpps.source_type', $sourceType))
            ->when($dateFrom, function ($query) use ($dateFrom) {
                $query->whereRaw('DATE(COALESCE(product_hpps.effective_at, product_hpps.created_at)) >= ?', [$dateFrom]);
            })
            ->when($dateTo, function ($query) use ($dateTo) {
                $query->whereRaw('DATE(COALESCE(product_hpps.effective_at, product_hpps.created_at)) <= ?', [$dateTo]);
            })
            ->orderByRaw('COALESCE(product_hpps.effective_at, product_hpps.created_at) DESC')
            ->orderByDesc('product_hpps.id')
            ->paginate(25)
            ->withQueryString();

        $products = DB::table('products')
            ->whereIn('id', function ($query) use ($branchId) {
                $query->select('product_id')
                    ->from('product_hpps')
                    ->where('branch_id', $branchId);
            })
            ->orderBy('product_name')
            ->get(['id', 'product_name', 'product_code']);

        $sourceTypes = DB::table('product_hpps')
            ->where('branch_id', $branchId)
            ->whereNotNull('source_type')
            ->where('source_type', '!=', '')
            ->distinct()
            ->orderBy('source_type')
            ->pluck('source_type');

        return view('product::hpp-ledger.index', [
            'rows' => $rows,
            'branches' => $availableBranches,
            'products' => $products,
            'sourceTypes' => $sourceTypes,
            'selectedBranchId' => $branchId,
            'selectedProductId' => $productId > 0 ? $productId : null,
            'selectedSourceType' => $sourceType,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }
}
