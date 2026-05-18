<?php

namespace Modules\Inventory\Http\Controllers;

use App\Support\ProductSearch;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Adjustment\Entities\AdjustedProduct;
use Modules\Adjustment\Entities\Adjustment;
use Modules\Inventory\Entities\StockOpname;
use Modules\Inventory\Entities\StockOpnameItem;
use Modules\Inventory\Exports\StockOpnameTemplateExport;
use Modules\Inventory\Imports\StockOpnameResultImport;
use Modules\Inventory\Services\StockOpnameService;
use Modules\Inventory\Entities\Rack;
use Modules\Mutation\Http\Controllers\MutationController;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;

class StockOpnameController extends Controller
{
    private StockOpnameService $service;
    private MutationController $mutationController;

    public function __construct(StockOpnameService $service, MutationController $mutationController)
    {
        $this->service = $service;
        $this->mutationController = $mutationController;
    }

    public function index()
    {
        abort_if(Gate::denies('access_inventories'), 403);

        $opnames = StockOpname::query()
            ->with(['branch:id,name', 'warehouse:id,warehouse_name', 'adjustment:id,reference'])
            ->latest('id')
            ->paginate(20);

        return view('inventory::stock-opnames.index', compact('opnames'));
    }

    public function create()
    {
        abort_if(Gate::denies('access_inventories'), 403);

        $branchId = $this->activeBranchId();
        $warehouses = Warehouse::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('is_main')
            ->orderBy('warehouse_name')
            ->get();

        $defaultWarehouseId = (int) (
            optional($warehouses->firstWhere('is_main', 1))->id
            ?? optional($warehouses->first())->id
            ?? 0
        );

        return view('inventory::stock-opnames.create', compact('warehouses', 'defaultWarehouseId'));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('access_inventories'), 403);

        $branchId = $this->activeBranchId();

        $request->validate([
            'opname_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'include_zero_stock' => ['nullable', 'boolean'],
        ]);

        $warehouse = $this->service->resolveWarehouseForBranch($branchId, (int) $request->warehouse_id);
        $includeZeroStock = (bool) $request->boolean('include_zero_stock');
        $rows = $this->service->buildDraftRows($branchId, $includeZeroStock);

        if ($rows->isEmpty()) {
            toast('Tidak ada produk kaca yang memenuhi kriteria draft opname.', 'error');
            return redirect()->back()->withInput();
        }

        $opname = DB::transaction(function () use ($branchId, $warehouse, $request, $rows) {
            $opname = StockOpname::query()->create([
                'branch_id' => $branchId,
                'warehouse_id' => (int) $warehouse->id,
                'reference' => $this->generateReference($branchId),
                'opname_date' => (string) $request->opname_date,
                'title' => trim((string) $request->title) !== ''
                    ? trim((string) $request->title)
                    : 'Stock Opname Kaca - ' . $warehouse->warehouse_name,
                'status' => 'draft',
                'note' => $request->note,
                'generated_at' => now(),
            ]);

            foreach ($rows as $row) {
                StockOpnameItem::query()->create([
                    'stock_opname_id' => (int) $opname->id,
                    'product_id' => (int) $row['product_id'],
                    'rack_id' => $row['rack_id'],
                    'product_code_snapshot' => $row['product_code_snapshot'],
                    'product_name_snapshot' => $row['product_name_snapshot'],
                    'rack_code_snapshot' => $row['rack_code_snapshot'],
                    'rack_name_snapshot' => $row['rack_name_snapshot'],
                    'system_qty' => (int) $row['system_qty'],
                ]);
            }

            return $opname;
        });

        toast('Draft stock opname berhasil dibuat.', 'success');
        return redirect()->route('inventory.stock-opnames.show', $opname);
    }

    public function show(Request $request, StockOpname $stockOpname)
    {
        abort_if(Gate::denies('access_inventories'), 403);

        $branchId = $this->activeBranchId(false);
        if ($branchId !== null && $branchId !== 'all' && (int) $stockOpname->branch_id !== (int) $branchId) {
            abort(403);
        }

        $search = trim((string) $request->get('search', ''));
        $status = trim((string) $request->get('status', ''));

        $itemsQuery = $stockOpname->items()->orderBy('product_code_snapshot');

        if ($search !== '') {
            $itemsQuery->where(function ($q) use ($search) {
                ProductSearch::applyTokenSearch(
                    $q,
                    $search,
                    ['product_code_snapshot', 'product_name_snapshot'],
                    'product_id'
                );
            });
        }

        if ($status !== '') {
            if ($status === 'missing_input') {
                $itemsQuery->whereNull('physical_qty');
            } elseif ($status === 'match') {
                $itemsQuery->whereNotNull('physical_qty')->where('diff_qty', 0);
            } elseif ($status === 'plus') {
                $itemsQuery->whereNotNull('physical_qty')->where('diff_qty', '>', 0);
            } elseif ($status === 'minus') {
                $itemsQuery->whereNotNull('physical_qty')->where('diff_qty', '<', 0);
            }
        }

        $items = $itemsQuery->paginate(50)->withQueryString();
        $summary = $this->buildSummary($stockOpname);
        $actionLinks = [];

        foreach ($items as $item) {
            $actionLinks[$item->id] = $this->resolveActionLink($stockOpname, $item);
        }

        return view('inventory::stock-opnames.show', compact('stockOpname', 'items', 'summary', 'search', 'status', 'actionLinks'));
    }

    public function downloadTemplate(StockOpname $stockOpname)
    {
        abort_if(Gate::denies('access_inventories'), 403);

        return Excel::download(
            new StockOpnameTemplateExport($stockOpname->fresh('items')),
            'stock_opname_' . strtolower($stockOpname->reference) . '.xlsx'
        );
    }

    public function import(Request $request, StockOpname $stockOpname)
    {
        abort_if(Gate::denies('access_inventories'), 403);
        abort_if($stockOpname->status !== 'draft', 422);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        try {
            $importer = new StockOpnameResultImport($stockOpname, $this->service, (int) (Auth::id() ?? 0));
            Excel::import($importer, $request->file('file'));

            if ($importer->getImportedCount() <= 0) {
                toast('Import selesai tetapi tidak ada qty fisik yang diperbarui.', 'error');
                return redirect()->back();
            }

            $stockOpname->update([
                'imported_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            toast('Hasil stock opname berhasil diimport.', 'success');
            return redirect()->route('inventory.stock-opnames.show', $stockOpname);
        } catch (\Throwable $e) {
            toast('Import stock opname gagal: ' . $e->getMessage(), 'error');
            return redirect()->back();
        }
    }

    public function searchProducts(Request $request, StockOpname $stockOpname)
    {
        abort_if(Gate::denies('access_inventories'), 403);

        $search = trim((string) $request->get('q', ''));
        if ($search === '') {
            return response()->json(['success' => true, 'data' => []]);
        }

        $products = Product::withoutGlobalScopes()
            ->where('item_type', 'glass')
            ->tap(function ($q) use ($search) {
                ProductSearch::applyTokenSearch($q, $search, ['product_code', 'product_name'], 'id');
            })
            ->orderBy('product_code')
            ->limit(20)
            ->get(['id', 'product_code', 'product_name']);

        $data = $products->map(function ($product) use ($stockOpname) {
            $existing = $stockOpname->items()
                ->where('product_id', $product->id)
                ->first(['system_qty', 'physical_qty', 'rack_code_snapshot', 'rack_name_snapshot']);

            return [
                'id' => (int) $product->id,
                'product_code' => (string) $product->product_code,
                'product_name' => (string) $product->product_name,
                'system_qty' => (int) ($existing->system_qty ?? 0),
                'physical_qty' => isset($existing) ? $existing->physical_qty : null,
                'rack_label' => isset($existing)
                    ? trim(((string) ($existing->rack_code_snapshot ?? '')) . ' - ' . ((string) ($existing->rack_name_snapshot ?? '')), ' -')
                    : '',
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeManualItem(Request $request, StockOpname $stockOpname)
    {
        abort_if(Gate::denies('access_inventories'), 403);
        abort_if($stockOpname->status !== 'draft', 422);

        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_code' => ['required', 'string', 'max:255'],
            'items.*.physical_qty' => ['required', 'integer', 'min:0'],
            'items.*.rack_code' => ['nullable', 'string', 'max:100'],
            'items.*.note' => ['nullable', 'string', 'max:1000'],
        ]);

        $warehouse = $this->service->resolveWarehouseForBranch((int) $stockOpname->branch_id, (int) $stockOpname->warehouse_id);

        DB::transaction(function () use ($request, $stockOpname, $warehouse) {
            foreach ((array) $request->input('items', []) as $row) {
                $productCode = strtoupper(trim((string) ($row['product_code'] ?? '')));
                $physicalQty = (int) ($row['physical_qty'] ?? 0);
                $rackCode = trim((string) ($row['rack_code'] ?? ''));
                $note = trim((string) ($row['note'] ?? ''));

                $product = Product::withoutGlobalScopes()
                    ->where('product_code', $productCode)
                    ->where('item_type', 'glass')
                    ->first(['id', 'product_code', 'product_name']);

                if (!$product) {
                    throw new \RuntimeException("Product code {$productCode} tidak ditemukan atau bukan item kaca.");
                }

                $item = StockOpnameItem::query()
                    ->where('stock_opname_id', $stockOpname->id)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                $snapshot = $this->service->buildSingleRow((int) $stockOpname->branch_id, (int) $product->id);
                if (!$snapshot) {
                    throw new \RuntimeException("Product {$productCode} tidak bisa dibuat sebagai row opname.");
                }

                $rackId = $snapshot['rack_id'];
                $rackCodeSnapshot = $snapshot['rack_code_snapshot'];
                $rackNameSnapshot = $snapshot['rack_name_snapshot'];

                if ($rackCode !== '') {
                    $rack = Rack::withoutGlobalScopes()
                        ->where('warehouse_id', (int) $warehouse->id)
                        ->where('code', $rackCode)
                        ->first(['id', 'code', 'name']);

                    if ($rack) {
                        $rackId = (int) $rack->id;
                        $rackCodeSnapshot = (string) ($rack->code ?? '');
                        $rackNameSnapshot = (string) ($rack->name ?? '');
                    }
                }

                if (!$item) {
                    $item = StockOpnameItem::query()->create([
                        'stock_opname_id' => (int) $stockOpname->id,
                        'product_id' => (int) $product->id,
                        'rack_id' => $rackId,
                        'product_code_snapshot' => (string) $product->product_code,
                        'product_name_snapshot' => (string) $product->product_name,
                        'rack_code_snapshot' => (string) $rackCodeSnapshot,
                        'rack_name_snapshot' => (string) $rackNameSnapshot,
                        'system_qty' => (int) ($snapshot['system_qty'] ?? 0),
                    ]);
                }

                $item->update([
                    'rack_id' => $rackId,
                    'rack_code_snapshot' => (string) $rackCodeSnapshot,
                    'rack_name_snapshot' => (string) $rackNameSnapshot,
                    'physical_qty' => $physicalQty,
                    'diff_qty' => $physicalQty - (int) $item->system_qty,
                    'review_status' => 'pending',
                    'resolution_type' => null,
                    'resolution_reference' => null,
                    'resolution_note' => null,
                    'resolved_at' => null,
                    'resolved_by' => null,
                    'note' => $note !== '' ? $note : $item->note,
                    'counted_at' => now(),
                ]);
            }
        });

        toast('Input fisik manual berhasil disimpan.', 'success');
        return redirect()->route('inventory.stock-opnames.show', $stockOpname);
    }

    public function markMissingAsZero(StockOpname $stockOpname)
    {
        abort_if(Gate::denies('access_inventories'), 403);
        abort_if($stockOpname->status !== 'draft', 422);

        $updated = $stockOpname->items()
            ->whereNull('physical_qty')
            ->where('system_qty', '>', 0)
            ->update([
                'physical_qty' => 0,
                'diff_qty' => DB::raw('0 - system_qty'),
                'review_status' => 'pending',
                'resolution_type' => null,
                'resolution_reference' => null,
                'resolution_note' => null,
                'resolved_at' => null,
                'resolved_by' => null,
                'counted_at' => now(),
                'updated_at' => now(),
            ]);

        toast($updated > 0
            ? 'Item yang belum diisi dan punya stok sistem sudah ditandai sebagai fisik 0.'
            : 'Tidak ada item kosong yang perlu ditandai 0.', $updated > 0 ? 'success' : 'info');

        return redirect()->route('inventory.stock-opnames.show', $stockOpname);
    }

    public function resolveItem(Request $request, StockOpname $stockOpname, StockOpnameItem $item)
    {
        abort_if(Gate::denies('access_inventories'), 403);
        abort_if($stockOpname->status !== 'draft', 422);
        abort_if((int) $item->stock_opname_id !== (int) $stockOpname->id, 404);

        $request->validate([
            'resolution_type' => ['required', 'string', 'in:missing_sale,missing_purchase,missing_transfer,rack_movement,adjustment,other'],
            'resolution_reference' => ['nullable', 'string', 'max:255'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if (is_null($item->physical_qty)) {
            toast('Qty fisik item ini belum diisi. Lengkapi dulu sebelum resolve.', 'error');
            return redirect()->route('inventory.stock-opnames.show', $stockOpname, ['status' => 'missing_input']);
        }

        $type = (string) $request->resolution_type;
        $reference = trim((string) $request->resolution_reference);
        $note = trim((string) $request->resolution_note);

        $item->update([
            'review_status' => 'resolved',
            'resolution_type' => $type,
            'resolution_reference' => $reference !== '' ? $reference : null,
            'resolution_note' => $note !== '' ? $note : null,
            'resolved_at' => now(),
            'resolved_by' => Auth::id(),
        ]);

        toast('Review item opname berhasil disimpan.', 'success');
        return redirect()->route('inventory.stock-opnames.show', $stockOpname);
    }

    public function updatePhysicalItem(Request $request, StockOpname $stockOpname, StockOpnameItem $item)
    {
        abort_if(Gate::denies('access_inventories'), 403);
        abort_if($stockOpname->status !== 'draft', 422);
        abort_if((int) $item->stock_opname_id !== (int) $stockOpname->id, 404);

        $request->validate([
            'physical_qty' => ['required', 'integer', 'min:0'],
        ]);

        $physicalQty = (int) $request->physical_qty;

        $item->update([
            'physical_qty' => $physicalQty,
            'diff_qty' => $physicalQty - (int) $item->system_qty,
            'review_status' => 'pending',
            'resolution_type' => null,
            'resolution_reference' => null,
            'resolution_note' => null,
            'resolved_at' => null,
            'resolved_by' => null,
            'counted_at' => now(),
        ]);

        toast('Qty fisik item berhasil diperbarui.', 'success');
        return redirect()->route('inventory.stock-opnames.show', $stockOpname, request()->only('search', 'status', 'page'));
    }

    public function resetResolve(StockOpname $stockOpname, StockOpnameItem $item)
    {
        abort_if(Gate::denies('access_inventories'), 403);
        abort_if($stockOpname->status !== 'draft', 422);
        abort_if((int) $item->stock_opname_id !== (int) $stockOpname->id, 404);

        $item->update([
            'review_status' => 'pending',
            'resolution_type' => null,
            'resolution_reference' => null,
            'resolution_note' => null,
            'resolved_at' => null,
            'resolved_by' => null,
        ]);

        toast('Resolve item opname berhasil di-reset.', 'success');
        return redirect()->route('inventory.stock-opnames.show', $stockOpname);
    }

    public function review(StockOpname $stockOpname)
    {
        abort_if(Gate::denies('access_inventories'), 403);
        abort_if($stockOpname->status !== 'draft', 422);

        $summary = $this->buildSummary($stockOpname);
        if ($summary['missing_input'] > 0) {
            toast('Masih ada item yang belum diisi fisiknya. Lengkapi dulu sebelum kunci review.', 'error');
            return redirect()->route('inventory.stock-opnames.show', $stockOpname);
        }

        if ($summary['unresolved_difference_count'] > 0) {
            toast('Masih ada item selisih yang belum direview. Resolve dulu sebelum kunci review.', 'error');
            return redirect()->route('inventory.stock-opnames.show', $stockOpname);
        }

        $stockOpname->update([
            'status' => 'reviewed',
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        toast('Stock opname berhasil dikunci ke tahap review. Qty fisik tidak bisa diubah lagi.', 'success');
        return redirect()->route('inventory.stock-opnames.show', $stockOpname);
    }

    public function finalize(Request $request, StockOpname $stockOpname)
    {
        abort_if(Gate::denies('create_adjustments'), 403);
        abort_if($stockOpname->status !== 'reviewed', 422);

        $summary = $this->buildSummary($stockOpname);
        if ($summary['adjustment_count'] <= 0) {
            toast('Tidak ada item adjustment untuk difinalisasi.', 'info');
            return redirect()->route('inventory.stock-opnames.show', $stockOpname);
        }

        DB::transaction(function () use ($stockOpname) {
            $branchId = (int) $stockOpname->branch_id;
            $warehouse = $this->service->resolveWarehouseForBranch($branchId, (int) $stockOpname->warehouse_id);
            $fallbackRack = $this->service->resolveDefaultRackSnapshot((int) $warehouse->id);

            $adjustment = Adjustment::query()->create([
                'date' => $stockOpname->opname_date,
                'warehouse_id' => (int) $warehouse->id,
                'branch_id' => $branchId,
                'note' => trim('Stock Opname ' . $stockOpname->reference . ($stockOpname->note ? ' | ' . $stockOpname->note : '')),
                'created_by' => Auth::id(),
            ]);

            $reference = (string) $adjustment->reference;

            $items = $stockOpname->items()
                ->whereNotNull('physical_qty')
                ->where('diff_qty', '!=', 0)
                ->where('review_status', 'resolved')
                ->where('resolution_type', 'adjustment')
                ->orderBy('product_code_snapshot')
                ->get();

            foreach ($items as $item) {
                $delta = (int) $item->diff_qty;
                if ($delta === 0) {
                    continue;
                }

                $rackId = $item->rack_id ?: ($fallbackRack['rack_id'] ?? null);
                if (!$rackId) {
                    throw new \RuntimeException('Rack default tidak ditemukan untuk opname finalisasi.');
                }

                $direction = $delta > 0 ? 'In' : 'Out';
                $qty = abs($delta);
                $note = trim(
                    'Stock Opname ' . $stockOpname->reference
                    . ' | Product ' . $item->product_code_snapshot
                    . ' | System=' . (int) $item->system_qty
                    . ' | Fisik=' . (int) $item->physical_qty
                    . ' | Selisih=' . $delta
                );

                $this->mutationController->applyInOut(
                    $branchId,
                    (int) $warehouse->id,
                    (int) $item->product_id,
                    $direction,
                    $qty,
                    $reference,
                    $note,
                    $stockOpname->opname_date,
                    (int) $rackId,
                    'good',
                    'summary'
                );

                AdjustedProduct::query()->create([
                    'adjustment_id' => (int) $adjustment->id,
                    'product_id' => (int) $item->product_id,
                    'warehouse_id' => (int) $warehouse->id,
                    'rack_id' => (int) $rackId,
                    'quantity' => $qty,
                    'type' => $delta > 0 ? 'add' : 'sub',
                    'note' => $note,
                ]);
            }

            $stockOpname->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'adjustment_id' => (int) $adjustment->id,
                'updated_by' => Auth::id(),
            ]);
        });

        toast('Stock opname berhasil difinalisasi menjadi adjustment + mutation.', 'success');
        return redirect()->route('inventory.stock-opnames.show', $stockOpname);
    }

    private function resolveActionLink(StockOpname $stockOpname, StockOpnameItem $item): ?array
    {
        if ($item->review_status !== 'resolved' || !$item->resolution_type) {
            return null;
        }

        $params = [
            'opname' => $stockOpname->reference,
            'opname_item' => $item->id,
            'product_code' => $item->product_code_snapshot,
            'product_name' => $item->product_name_snapshot,
            'qty_system' => (int) $item->system_qty,
            'qty_physical' => (int) $item->physical_qty,
            'qty_diff' => (int) $item->diff_qty,
            'branch_id' => (int) $stockOpname->branch_id,
        ];

        return match ($item->resolution_type) {
            'missing_sale' => [
                'label' => 'Buat Penjualan',
                'url' => route('sales.create', $params),
                'style' => 'primary',
            ],
            'missing_purchase' => [
                'label' => 'Buat Pembelian',
                'url' => route('purchases.create', $params),
                'style' => 'success',
            ],
            'missing_transfer' => [
                'label' => 'Buat Transfer',
                'url' => route('transfers.create', $params),
                'style' => 'info',
            ],
            'rack_movement' => [
                'label' => 'Buat Rack Movement',
                'url' => route('inventory.rack-movements.create', $params),
                'style' => 'dark',
            ],
            'adjustment' => [
                'label' => $stockOpname->status === 'reviewed' ? 'Siap Finalize Adjustment' : 'Akan Masuk Adjustment',
                'url' => null,
                'style' => 'warning',
            ],
            default => null,
        };
    }

    private function activeBranchId(bool $mustBeSpecific = true)
    {
        $active = session('active_branch');

        if ($mustBeSpecific && ($active === 'all' || $active === null || $active === '')) {
            abort(422, 'Please select a specific branch first.');
        }

        return $active;
    }

    private function generateReference(int $branchId): string
    {
        $branchCode = match ($branchId) {
            1 => 'BKS',
            2 => 'SBY',
            3 => 'TGR',
            default => 'BR' . $branchId,
        };

        return 'OPN-' . $branchCode . '-' . now()->format('Ymd-His');
    }

    private function buildSummary(StockOpname $stockOpname): array
    {
        $base = $stockOpname->items();

        return [
            'total_items' => (clone $base)->count(),
            'counted_items' => (clone $base)->whereNotNull('physical_qty')->count(),
            'missing_input' => (clone $base)->whereNull('physical_qty')->count(),
            'match_count' => (clone $base)->whereNotNull('physical_qty')->where('diff_qty', 0)->count(),
            'plus_count' => (clone $base)->whereNotNull('physical_qty')->where('diff_qty', '>', 0)->count(),
            'minus_count' => (clone $base)->whereNotNull('physical_qty')->where('diff_qty', '<', 0)->count(),
            'difference_count' => (clone $base)->whereNotNull('physical_qty')->where('diff_qty', '!=', 0)->count(),
            'resolved_difference_count' => (clone $base)->whereNotNull('physical_qty')->where('diff_qty', '!=', 0)->where('review_status', 'resolved')->count(),
            'unresolved_difference_count' => (clone $base)->whereNotNull('physical_qty')->where('diff_qty', '!=', 0)->where(function ($q) {
                $q->whereNull('review_status')->orWhere('review_status', 'pending');
            })->count(),
            'adjustment_count' => (clone $base)->whereNotNull('physical_qty')->where('diff_qty', '!=', 0)->where('review_status', 'resolved')->where('resolution_type', 'adjustment')->count(),
            'system_total' => (int) ((clone $base)->sum('system_qty') ?? 0),
            'physical_total' => (int) ((clone $base)->whereNotNull('physical_qty')->sum('physical_qty') ?? 0),
        ];
    }
}
