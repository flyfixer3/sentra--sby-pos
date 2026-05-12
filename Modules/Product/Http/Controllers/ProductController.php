<?php

namespace Modules\Product\Http\Controllers;

use App\Support\BranchContext;
use Modules\Product\DataTables\ProductDataTable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Product\Entities\Accessory;
use Modules\Product\Entities\Product;
use Modules\Product\Services\HppService;
use Modules\Mutation\Entities\Mutation;
use Modules\Product\Http\Requests\StoreProductRequest;
use Modules\Product\Http\Requests\UpdateProductRequest;
use Modules\Upload\Entities\Upload;

class ProductController extends Controller
{
    private function normalizePricePayload(array $payload): array
    {
        $basePrice = $payload['product_price'] ?? null;

        if (($payload['product_price_item_only'] ?? null) === null || $payload['product_price_item_only'] === '') {
            $payload['product_price_item_only'] = $basePrice;
        }

        if (($payload['product_price_package'] ?? null) === null || $payload['product_price_package'] === '') {
            $payload['product_price_package'] = $basePrice;
        }

        return $payload;
    }

    private function resolveAccessorySelection(Request $request, ?Product $product = null): array
    {
        $selectedIds = collect((array) $request->input('accessory_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        if ($selectedIds->isNotEmpty()) {
            $selected = Accessory::query()
                ->whereIn('id', $selectedIds->all())
                ->orderByRaw('FIELD(id,' . $selectedIds->implode(',') . ')')
                ->get(['id', 'accessory_code']);

            if ($selected->isNotEmpty()) {
                return [
                    'primary_code' => (string) $selected->first()->accessory_code,
                    'ids' => $selected->pluck('id')->map(fn ($id) => (int) $id)->all(),
                ];
            }
        }

        $legacyCode = trim((string) $request->input('accessory_code', $product?->accessory_code ?? ''));
        if ($legacyCode !== '') {
            $legacy = Accessory::query()->where('accessory_code', $legacyCode)->first();
            if ($legacy) {
                return [
                    'primary_code' => (string) $legacy->accessory_code,
                    'ids' => [(int) $legacy->id],
                ];
            }
        }

        throw ValidationException::withMessages([
            'accessory_ids' => 'Select at least one accessory.',
        ]);
    }

    public function index(ProductDataTable $dataTable) {
        abort_if(Gate::denies('access_products'), 403);

        return $dataTable->render('product::products.index');
    }


    public function create() {
        abort_if(Gate::denies('create_products'), 403);

        $accessories = Accessory::query()
            ->orderBy('accessory_code')
            ->get();

        return view('product::products.create', compact('accessories'));
    }


    public function store(StoreProductRequest $request) {
        $accessorySelection = $this->resolveAccessorySelection($request);

        $payload = $request->except(['document', 'accessory_ids']);
        $payload['accessory_code'] = $accessorySelection['primary_code'];
        $payload = $this->normalizePricePayload($payload);

        $product = Product::create($payload);
        $product->accessories()->sync($accessorySelection['ids']);

        if ($request->has('document')) {
            foreach ($request->input('document', []) as $file) {
                $product->addMedia(Storage::path('temp/dropzone/' . $file))->toMediaCollection('images');
            }
        }

        toast('Product Created!', 'success');

        return redirect()->route('products.index');
    }


    public function show(Product $product) {
        abort_if(Gate::denies('show_products'), 403);
        $product->loadMissing(['creator', 'updater', 'accessories', 'brand']);

        $activeBranchId = BranchContext::id();
        $currentBranchHpp = null;
        $currentBranchStockOnHand = null;
        $stockLocationHints = collect();

        if (!empty($activeBranchId)) {
            $currentBranchHpp = (new HppService())->getCurrentHpp((int) $activeBranchId, (int) $product->id);
            $currentBranchStockOnHand = (int) DB::table('stock_racks')
                ->where('branch_id', (int) $activeBranchId)
                ->where('product_id', (int) $product->id)
                ->sum('qty_total');

            $stockLocationHints = DB::table('stock_racks as sr')
                ->leftJoin('branches as b', 'b.id', '=', 'sr.branch_id')
                ->leftJoin('warehouses as w', 'w.id', '=', 'sr.warehouse_id')
                ->leftJoin('racks as r', 'r.id', '=', 'sr.rack_id')
                ->where('sr.branch_id', (int) $activeBranchId)
                ->where('sr.product_id', (int) $product->id)
                ->whereRaw('COALESCE(sr.qty_total, 0) > 0')
                ->orderBy('w.warehouse_name')
                ->orderBy('r.code')
                ->select([
                    DB::raw('COALESCE(b.name, "-") as branch_name'),
                    DB::raw('COALESCE(w.warehouse_name, "-") as warehouse_name'),
                    DB::raw('COALESCE(r.code, "") as rack_code'),
                    DB::raw('COALESCE(r.name, "-") as rack_name'),
                    DB::raw('COALESCE(sr.qty_total, 0) as qty_total'),
                    DB::raw('COALESCE(sr.qty_good, 0) as qty_good'),
                    DB::raw('COALESCE(sr.qty_defect, 0) as qty_defect'),
                    DB::raw('COALESCE(sr.qty_damaged, 0) as qty_damaged'),
                ])
                ->get();
        }

        return view('product::products.show', compact(
            'product',
            'currentBranchHpp',
            'currentBranchStockOnHand',
            'activeBranchId',
            'stockLocationHints'
        ));
    }


    public function edit(Product $product) {
        abort_if(Gate::denies('edit_products'), 403);

        $product->loadMissing('accessories');
        $accessories = Accessory::query()
            ->orderBy('accessory_code')
            ->get();

        return view('product::products.edit', compact('product', 'accessories'));
    }


    public function update(UpdateProductRequest $request, Product $product) {
        $accessorySelection = $this->resolveAccessorySelection($request, $product);

        $payload = $request->except(['document', 'accessory_ids']);
        $payload['accessory_code'] = $accessorySelection['primary_code'];
        $payload = $this->normalizePricePayload($payload);

        $product->update($payload);
        $product->accessories()->sync($accessorySelection['ids']);

        if ($request->has('document')) {
            if (count($product->getMedia('images')) > 0) {
                foreach ($product->getMedia('images') as $media) {
                    if (!in_array($media->file_name, $request->input('document', []))) {
                        $media->delete();
                    }
                }
            }

            $media = $product->getMedia('images')->pluck('file_name')->toArray();

            foreach ($request->input('document', []) as $file) {
                if (count($media) === 0 || !in_array($file, $media)) {
                    $product->addMedia(Storage::path('temp/dropzone/' . $file))->toMediaCollection('images');
                }
            }
        }

        toast('Product Updated!', 'info');

        return redirect()->route('products.index');
    }


    public function destroy(Product $product) {
        abort_if(Gate::denies('delete_products'), 403);

        $product->delete();

        toast('Product Deleted!', 'warning');

        return redirect()->route('products.index');
    }
}
