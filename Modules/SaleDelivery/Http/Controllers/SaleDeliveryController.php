<?php

namespace Modules\SaleDelivery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use Modules\People\Entities\Customer;
use Modules\Product\Entities\Warehouse;
use Modules\Product\Entities\Product;
use Modules\Mutation\Entities\Mutation;
use Modules\SaleDelivery\DataTables\SaleDeliveriesDataTable;
use Modules\SaleDelivery\Entities\SaleDelivery;
use Modules\SaleDelivery\Entities\SaleDeliveryItem;
use Modules\SaleDelivery\Http\Controllers\Concerns\SaleDeliveryShared;
use Modules\SaleOrder\Entities\SaleOrder;
use Modules\SaleOrder\Entities\SaleOrderItem;

class SaleDeliveryController extends Controller
{
    use SaleDeliveryShared;

    private function normalizeDraftItems(array $items): array
    {
        return collect($items)
            ->map(function ($row) {
                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'sale_order_item_id' => !empty($row['sale_order_item_id']) ? (int) $row['sale_order_item_id'] : null,
                    'sale_item_id' => !empty($row['sale_item_id']) ? (int) $row['sale_item_id'] : null,
                    'quantity' => max(0, (int) ($row['quantity'] ?? 0)),
                    'price' => array_key_exists('price', $row) && $row['price'] !== null
                        ? (int) $row['price']
                        : null,
                ];
            })
            ->filter(function ($row) {
                return (int) $row['product_id'] > 0 && (int) $row['quantity'] > 0;
            })
            ->values()
            ->all();
    }

    public function index(SaleDeliveriesDataTable $dataTable)
    {
        abort_if(Gate::denies('access_sale_deliveries'), 403);
        return $dataTable->render('saledelivery::index');
    }

    public function show(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('show_sale_deliveries'), 403);

        $saleDelivery->load([
            'items.product',
            'items.saleOrderItem.customerVehicle',
            'items.saleItem.customerVehicle',
            'warehouse',
            'customer',
            'creator',
            'confirmer',
            'saleOrder',
        ]);

        $mutations = Mutation::withoutGlobalScopes()
            ->with(['warehouse', 'product', 'rack'])
            ->where('branch_id', (int) $saleDelivery->branch_id)
            ->where('reference', (string) $saleDelivery->reference)
            ->where('note', 'like', 'Sales Delivery OUT #%')
            ->orderBy('id', 'asc')
            ->get();

        $pickedDefectItems = DB::table('product_defect_items as pdi')
            ->leftJoin('products as p', 'p.id', '=', 'pdi.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'pdi.warehouse_id')
            ->leftJoin('racks as r', 'r.id', '=', 'pdi.rack_id')
            ->leftJoin('users as u', 'u.id', '=', 'pdi.moved_out_by')
            ->where('pdi.moved_out_reference_type', SaleDelivery::class)
            ->where('pdi.moved_out_reference_id', (int) $saleDelivery->id)
            ->orderBy('pdi.id')
            ->get([
                'pdi.*',
                'p.product_name',
                'p.product_code',
                'w.warehouse_name',
                'r.code as rack_code',
                'r.name as rack_name',
                'u.name as moved_out_by_name',
            ]);

        $pickedDamagedItems = DB::table('product_damaged_items as pdi')
            ->leftJoin('products as p', 'p.id', '=', 'pdi.product_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'pdi.warehouse_id')
            ->leftJoin('racks as r', 'r.id', '=', 'pdi.rack_id')
            ->leftJoin('users as u', 'u.id', '=', 'pdi.moved_out_by')
            ->where('pdi.moved_out_reference_type', SaleDelivery::class)
            ->where('pdi.moved_out_reference_id', (int) $saleDelivery->id)
            ->orderBy('pdi.id')
            ->get([
                'pdi.*',
                'p.product_name',
                'p.product_code',
                'w.warehouse_name',
                'r.code as rack_code',
                'r.name as rack_name',
                'u.name as moved_out_by_name',
            ]);

        return view('saledelivery::show', compact('saleDelivery', 'mutations', 'pickedDefectItems', 'pickedDamagedItems'));
    }

    public function create(Request $request)
    {
        abort_if(Gate::denies('create_sale_deliveries'), 403);

        try {
            $source = (string) $request->get('source', '');

            if (!in_array($source, ['quotation', 'sale', 'sale_order'], true)) {
                throw new \RuntimeException('Sale Delivery can only be created from Quotation, Sale, or Sale Order.');
            }

            if ($source === 'quotation' && !$request->filled('quotation_id')) {
                throw new \RuntimeException('quotation_id is required');
            }

            if ($source === 'sale' && !$request->filled('sale_id')) {
                throw new \RuntimeException('sale_id is required');
            }

            if ($source === 'sale_order' && !$request->filled('sale_order_id')) {
                throw new \RuntimeException('sale_order_id is required');
            }

            $branchId = BranchContext::id();

            $customers = Customer::query()
                ->forActiveBranch($branchId)
                ->orderBy('customer_name')
                ->get();

            $prefillItems = [];
            $prefillCustomerId = null;
            $prefillSaleOrderRef = null;

            if ($source === 'sale_order') {
                $saleOrderId = (int) $request->sale_order_id;

                $saleOrder = SaleOrder::query()
                    ->where('id', $saleOrderId)
                    ->where('branch_id', $branchId)
                    ->with(['items.product', 'items.customerVehicle'])
                    ->firstOrFail();

                $prefillSaleOrderRef = $saleOrder->reference ?? ('SO#' . $saleOrder->id);
                $prefillCustomerId = (int) $saleOrder->customer_id;

                $remainingMap = $this->getPlannedRemainingQtyBySaleOrderItem($saleOrderId);

                $hasAny = false;
                foreach ($remainingMap as $v) {
                    if ((int) $v > 0) {
                        $hasAny = true;
                        break;
                    }
                }

                if (!$hasAny) {
                    throw new \RuntimeException('All items are already planned in existing deliveries (pending/confirmed/partial).');
                }

                foreach ($saleOrder->items as $it) {
                    $itemId = (int) $it->id;
                    $pid = (int) ($it->product_id ?? 0);
                    $rem = (int) ($remainingMap[$itemId] ?? 0);

                    if ($itemId <= 0 || $pid <= 0 || $rem <= 0) {
                        continue;
                    }

                    $prefillItems[] = [
                        'product_id' => $pid,
                        'sale_order_item_id' => $itemId,
                        'product_name' => $it->product?->product_name,
                        'product_code' => $it->product?->product_code,
                        'quantity' => $rem,
                        'price' => (int) ($it->price ?? 0),
                        'unit_price' => (int) ($it->unit_price ?? $it->price ?? 0),
                        'installation_type' => (string) ($it->installation_type ?? 'item_only'),
                        'customer_vehicle_id' => $it->customer_vehicle_id,
                        'vehicle_label' => $it->customerVehicle
                            ? trim(implode(' ', array_filter([
                                $it->customerVehicle->vehicle_name ?? null,
                                $it->customerVehicle->license_number ?? null,
                                $it->customerVehicle->car_plate ?? null,
                            ])))
                            : null,
                    ];
                }
            }

            if ($source === 'sale') {
                $saleId = (int) $request->sale_id;

                $sale = DB::table('sales')
                    ->where('id', $saleId)
                    ->where('branch_id', $branchId)
                    ->first();

                if (!$sale) {
                    throw new \RuntimeException('Sale (invoice) not found in this branch.');
                }

                $prefillCustomerId = (int) ($sale->customer_id ?? 0);

                $remainingByItem = $this->getRemainingQtyBySaleItem($saleId);

                $details = DB::table('sale_details')
                    ->where('sale_id', $saleId)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->get();

                $productIds = $details
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                $productMap = Product::withoutGlobalScopes()
                    ->whereIn('id', $productIds)
                    ->get(['id', 'product_name', 'product_code'])
                    ->keyBy('id');

                $remainingByProduct = $this->getRemainingQtyBySale($saleId);

                foreach ($details as $detail) {
                    $detailId = (int) ($detail->id ?? 0);
                    $pid = (int) ($detail->product_id ?? 0);
                    if (!empty($remainingByItem)) {
                        $rem = (int) ($remainingByItem[$detailId] ?? 0);
                    } else {
                        $available = max(0, (int) ($remainingByProduct[$pid] ?? 0));
                        $rem = min(max(0, (int) ($detail->quantity ?? 0)), $available);
                        $remainingByProduct[$pid] = max(0, $available - $rem);
                    }

                    if ($detailId <= 0 || $pid <= 0 || $rem <= 0) {
                        continue;
                    }

                    $product = $productMap->get($pid);

                    $prefillItems[] = [
                        'product_id' => $pid,
                        'sale_item_id' => $detailId,
                        'product_name' => $product?->product_name,
                        'product_code' => $product?->product_code,
                        'quantity' => $rem,
                        'price' => (int) ($detail->price ?? 0),
                        'unit_price' => (int) ($detail->unit_price ?? $detail->price ?? 0),
                        'installation_type' => (string) ($detail->installation_type ?? 'item_only'),
                        'customer_vehicle_id' => $detail->customer_vehicle_id ?? null,
                    ];
                }
            }

            /*
            * Product dropdown tetap dibutuhkan untuk flow non-Sale Order.
            * Tetapi selected/prefill product wajib ikut dimasukkan walaupun tidak masuk limit 500,
            * supaya tampilan tidak blank.
            */
            $products = Product::query()
                ->orderBy('product_name')
                ->limit(500)
                ->get();

            $prefillProductIds = collect($prefillItems)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            if ($prefillProductIds->isNotEmpty()) {
                $prefillProducts = Product::withoutGlobalScopes()
                    ->whereIn('id', $prefillProductIds->all())
                    ->get();

                $products = $products
                    ->merge($prefillProducts)
                    ->unique('id')
                    ->sortBy('product_name')
                    ->values();
            }

            return view('saledelivery::create', compact(
                'customers',
                'products',
                'source',
                'prefillItems',
                'prefillCustomerId',
                'prefillSaleOrderRef'
            ));
        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_sale_deliveries'), 403);

        try {
            $source = (string) $request->get('source', '');
            if (!in_array($source, ['quotation', 'sale', 'sale_order'], true)) {
                throw new \RuntimeException('Invalid source.');
            }

            $branchId = BranchContext::id();

            $rules = [
                'date' => 'required|date',
                'note' => 'nullable|string|max:2000',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer',
                'items.*.sale_order_item_id' => 'nullable|integer',
                'items.*.sale_item_id' => 'nullable|integer',
                'items.*.quantity' => 'required|integer|min:0',
                'items.*.price' => 'nullable|integer|min:0',
            ];

            if ($source !== 'sale_order') $rules['customer_id'] = 'required|integer';
            if ($source === 'quotation') $rules['quotation_id'] = 'required|integer';
            if ($source === 'sale') $rules['sale_id'] = 'required|integer';
            if ($source === 'sale_order') $rules['sale_order_id'] = 'required|integer';

            $request->validate($rules);

            DB::transaction(function () use ($request, $branchId, $source) {
                $deliveryItems = $this->normalizeDraftItems((array) $request->input('items', []));
                if (empty($deliveryItems)) {
                    throw new \RuntimeException('At least 1 product with quantity greater than 0 is required.');
                }

                $saleId = null;
                $saleOrderId = null;
                $customerId = null;

                if ($source === 'sale_order') {
                    $saleOrderId = (int) $request->sale_order_id;

                    $saleOrder = SaleOrder::query()
                        ->where('id', $saleOrderId)
                        ->where('branch_id', $branchId)
                        ->with(['items'])
                        ->firstOrFail();

                    $customerId = (int) $saleOrder->customer_id;

                    $saleOrderItems = $saleOrder->items->keyBy('id');
                    $remainingMap = $this->getPlannedRemainingQtyBySaleOrderItem($saleOrderId);

                    foreach ($deliveryItems as $row) {
                        $saleOrderItemId = (int) ($row['sale_order_item_id'] ?? 0);
                        $pid = (int) $row['product_id'];
                        $qty = (int) $row['quantity'];

                        if ($saleOrderItemId <= 0 || !$saleOrderItems->has($saleOrderItemId)) {
                            throw new \RuntimeException('Submitted Sale Order item does not belong to the selected Sale Order.');
                        }

                        $saleOrderItem = $saleOrderItems->get($saleOrderItemId);
                        if ((int) ($saleOrderItem->product_id ?? 0) !== $pid) {
                            throw new \RuntimeException("Product mismatch for sale_order_item_id {$saleOrderItemId}.");
                        }

                        $rem = (int) ($remainingMap[$saleOrderItemId] ?? 0);

                        if ($qty > $rem) {
                            throw new \RuntimeException("Qty exceeds PLANNED remaining for sale_order_item_id {$saleOrderItemId}. Remaining: {$rem}.");
                        }
                    }
                }

                if ($source === 'sale') {
                    $saleId = (int) $request->sale_id;

                    $sale = DB::table('sales')
                        ->where('id', $saleId)
                        ->where('branch_id', $branchId)
                        ->first();

                    if (!$sale) throw new \RuntimeException('Sale (invoice) not found in this branch.');

                    $saleDetails = DB::table('sale_details')
                        ->where('sale_id', $saleId)
                        ->whereNull('deleted_at')
                        ->get()
                        ->keyBy('id');

                    $remainingByItem = $this->getRemainingQtyBySaleItem($saleId);
                    $remainingByProduct = $this->getRemainingQtyBySale($saleId);

                    foreach ($deliveryItems as $row) {
                        $saleItemId = (int) ($row['sale_item_id'] ?? 0);
                        $pid = (int) $row['product_id'];
                        $qty = (int) $row['quantity'];

                        if ($saleItemId > 0) {
                            if (!$saleDetails->has($saleItemId)) {
                                throw new \RuntimeException('Submitted Sale item does not belong to the selected Sale.');
                            }

                            $saleItem = $saleDetails->get($saleItemId);
                            if ((int) ($saleItem->product_id ?? 0) !== $pid) {
                                throw new \RuntimeException("Product mismatch for sale_item_id {$saleItemId}.");
                            }

                            $rem = (int) ($remainingByItem[$saleItemId] ?? 0);
                        } else {
                            $rem = (int) ($remainingByProduct[$pid] ?? 0);
                        }

                        if ($qty > $rem) {
                            throw new \RuntimeException("Qty exceeds remaining for product_id {$pid}. Remaining: {$rem}.");
                        }

                        if ($saleItemId <= 0) {
                            $remainingByProduct[$pid] = max(0, $rem - $qty);
                        }
                    }

                    $customerId = (int) ($sale->customer_id ?? 0);
                }

                if ($source === 'quotation') {
                    $customerId = (int) $request->customer_id;
                }

                $saleDelivery = SaleDelivery::create([
                    'branch_id'     => (int) $branchId,
                    'quotation_id'  => $source === 'quotation' ? (int) $request->quotation_id : null,
                    'sale_order_id' => $source === 'sale_order' ? (int) $request->sale_order_id : null,
                    'sale_id'       => $source === 'sale' ? (int) $request->sale_id : null,

                    'customer_id'   => (int) $customerId,
                    'reference'     => null,
                    'date'          => (string) $request->date,

                    'warehouse_id'  => null,
                    'status'        => 'pending',
                    'note'          => $request->note ? (string) $request->note : null,
                    'created_by'    => auth()->id(),
                ]);

                $createdItems = [];

                foreach ($deliveryItems as $row) {

                    // ✅ FIX: 0 / negatif dianggap "tidak diisi" => NULL
                    $price = array_key_exists('price', $row) && $row['price'] !== null
                        ? (int) $row['price']
                        : null;

                    if ($price !== null && $price <= 0) {
                        $price = null;
                    }

                    $createdItems[] = SaleDeliveryItem::create([
                        'sale_delivery_id' => (int) $saleDelivery->id,
                        'product_id'       => (int) $row['product_id'],
                        'sale_order_item_id' => !empty($row['sale_order_item_id']) ? (int) $row['sale_order_item_id'] : null,
                        'sale_item_id'     => !empty($row['sale_item_id']) ? (int) $row['sale_item_id'] : null,
                        'quantity'         => (int) $row['quantity'],
                        'price'            => $price,
                        'qty_good'         => 0,
                        'qty_defect'       => 0,
                        'qty_damaged'      => 0,
                    ]);
                }

            });

            toast('Sale Delivery created successfully', 'success');
            return redirect()->route('sale-deliveries.index');
        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    public function edit(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('edit_sale_deliveries'), 403);

        try {
            $this->ensureSpecificBranchSelected();

            if (strtolower((string) $saleDelivery->status) !== 'pending') {
                throw new \RuntimeException('Only pending Sale Delivery can be edited.');
            }

            $branchId = BranchContext::id();
            if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                throw new \RuntimeException('Wrong branch context.');
            }

            $saleDelivery->load(['items.product', 'warehouse', 'customer', 'saleOrder']);

            $warehouses = Warehouse::query()
                ->where('branch_id', $branchId)
                ->orderBy('warehouse_name')
                ->get();

            $customers = Customer::query()
                ->forActiveBranch($branchId)
                ->orderBy('customer_name')
                ->get();

            $products = Product::query()->orderBy('product_name')->limit(500)->get();

            return view('saledelivery::edit', compact('saleDelivery', 'warehouses', 'customers', 'products'));
        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    public function update(Request $request, SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('edit_sale_deliveries'), 403);

        try {
            $this->ensureSpecificBranchSelected();

            if (strtolower((string) $saleDelivery->status) !== 'pending') {
                throw new \RuntimeException('Only pending Sale Delivery can be edited.');
            }

            $branchId = BranchContext::id();

            if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                throw new \RuntimeException('Wrong branch context.');
            }

            $request->validate([
                'date' => 'required|date',
                'warehouse_id' => 'nullable|integer',
                'note' => 'nullable|string|max:2000',
            ]);

            DB::transaction(function () use ($request, $saleDelivery, $branchId) {
                $saleDelivery = SaleDelivery::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->with(['items'])
                    ->findOrFail($saleDelivery->id);

                $st = strtolower(trim((string) ($saleDelivery->getRawOriginal('status') ?? $saleDelivery->status ?? 'pending')));

                if ($st !== 'pending') {
                    throw new \RuntimeException('Only pending Sale Delivery can be edited.');
                }

                if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                    throw new \RuntimeException('Wrong branch context.');
                }

                $warehouseId = null;

                if (!empty($request->warehouse_id)) {
                    $warehouse = Warehouse::query()
                        ->where('branch_id', $branchId)
                        ->where('id', (int) $request->warehouse_id)
                        ->firstOrFail();

                    $warehouseId = (int) $warehouse->id;
                }

                /*
                * Penting:
                * Edit Sale Delivery hanya mengubah header.
                * Items tidak disentuh karena view edit menampilkan items readonly.
                * Pengurangan stok tetap hanya terjadi saat Confirm.
                */
                $saleDelivery->update([
                    'date' => $request->date,
                    'warehouse_id' => $warehouseId,
                    'note' => $request->note,
                    'updated_by' => auth()->id(),
                ]);
            });

            toast('Sale Delivery Updated!', 'success');
            return redirect()->route('sale-deliveries.show', $saleDelivery->id);

        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    public function createInvoice(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('create_sales'), 403);

        // boleh tetap POST (lebih aman), tapi tugasnya cuma redirect ke page create
        if (!request()->isMethod('post')) {
            abort(405);
        }

        try {
            $this->ensureSpecificBranchSelected();
            $branchId = BranchContext::id();

            // pastikan branch bener
            if ((int) $saleDelivery->branch_id !== (int) $branchId) {
                throw new \RuntimeException('Wrong branch context.');
            }

            // wajib confirmed
            $st = strtolower(trim((string) ($saleDelivery->getRawOriginal('status') ?? $saleDelivery->status ?? 'pending')));
            if ($st !== 'confirmed') {
                throw new \RuntimeException('Invoice can be created only when Sale Delivery is CONFIRMED.');
            }

            // kalau sudah ada invoice, langsung arahkan ke invoice
            if (!empty($saleDelivery->sale_id)) {
                toast('Invoice already created for this Sale Delivery.', 'info');
                return redirect()->route('sales.show', (int) $saleDelivery->sale_id);
            }

            // ✅ inti: redirect ke Sale Create (prefill)
            return redirect()->route('sales.create', [
                'sale_delivery_id' => (int) $saleDelivery->id,
            ]);

        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    public function destroy(SaleDelivery $saleDelivery)
    {
        abort_if(Gate::denies('delete_sale_deliveries'), 403);

        try {
            $this->ensureSpecificBranchSelected();
            $branchId = BranchContext::id();

            if ((int) ($saleDelivery->branch_id ?? 0) !== (int) $branchId) {
                throw new \RuntimeException('Wrong branch context.');
            }

            $st = strtolower(trim((string) ($saleDelivery->getRawOriginal('status') ?? $saleDelivery->status ?? 'pending')));
            if ($st === 'confirmed') {
                throw new \RuntimeException('Cannot delete. Sale Delivery is already confirmed.');
            }

            DB::transaction(function () use ($saleDelivery, $branchId) {
                $saleDelivery = SaleDelivery::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->with(['items'])
                    ->findOrFail((int) $saleDelivery->id);

                if ((int) ($saleDelivery->branch_id ?? 0) !== (int) $branchId) {
                    throw new \RuntimeException('Wrong branch context.');
                }

                $st = strtolower(trim((string) ($saleDelivery->getRawOriginal('status') ?? $saleDelivery->status ?? 'pending')));
                if ($st === 'confirmed') {
                    throw new \RuntimeException('Cannot delete. Sale Delivery is already confirmed.');
                }

                if (method_exists($saleDelivery, 'items')) {
                    $saleDelivery->items()->delete();
                } else {
                    SaleDeliveryItem::query()
                        ->where('sale_delivery_id', (int) $saleDelivery->id)
                        ->delete();
                }

                $saleDelivery->delete();
            });

            toast('Sale Delivery deleted!', 'warning');
            return redirect()->route('sale-deliveries.index');

        } catch (\Throwable $e) {
            toast($e->getMessage(), 'error');
            return back();
        }
    }
}
