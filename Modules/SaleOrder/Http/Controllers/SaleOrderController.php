<?php

namespace Modules\SaleOrder\Http\Controllers;

use App\Support\BranchContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Crm\Entities\Lead;
use Modules\People\Entities\Customer;
use Modules\People\Entities\CustomerVehicle;
use Modules\Branch\Entities\Branch;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;
use Modules\Quotation\Entities\Quotation;
use Modules\Sale\Entities\Sale;
use Modules\SaleOrder\DataTables\SaleOrdersDataTable;
use Modules\SaleOrder\Entities\SaleOrder;
use Modules\SaleOrder\Entities\SaleOrderItem;

class SaleOrderController extends Controller
{
    private function deliveredQtyExpr(string $alias = 'sdi'): string
    {
        return "CASE
            WHEN (COALESCE({$alias}.qty_good,0) + COALESCE({$alias}.qty_defect,0) + COALESCE({$alias}.qty_damaged,0)) > 0
                THEN (COALESCE({$alias}.qty_good,0) + COALESCE({$alias}.qty_defect,0) + COALESCE({$alias}.qty_damaged,0))
            ELSE COALESCE({$alias}.quantity,0)
        END";
    }

    private function saleOrderPdfFilename(SaleOrder $saleOrder): string
    {
        $ref = trim((string) ($saleOrder->reference ?? ''));
        if ($ref === '') {
            $ref = 'sale-order-' . (int) $saleOrder->id;
        }

        return 'sale-order-' . $ref . '.pdf';
    }

    private function buildSaleOrderPdf(SaleOrder $saleOrder)
    {
        $saleOrder->loadMissing([
            'customer',
            'items.product',
            'items.customerVehicle',
        ]);

        $branch = !empty($saleOrder->branch_id)
            ? Branch::withoutGlobalScopes()->find((int) $saleOrder->branch_id)
            : null;

        return Pdf::loadView('saleorder::print', [
            'saleOrder' => $saleOrder,
            'customer' => $saleOrder->customer,
            'branch' => $branch,
        ])->setPaper('a4');
    }

    private function failBack(string $message, int $status = 422)
    {
        toast($message, 'error');
        return redirect()->back()->withInput();
    }

    private function buildSaleOrderItemSnapshots(array $rows, $productMap): array
    {
        $items = [];
        $qtyByProduct = [];
        $sellSubtotal = 0;
        $masterSubtotal = 0;

        foreach ($rows as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);

            if ($pid <= 0 || $qty <= 0) {
                continue;
            }

            $product = $productMap[$pid] ?? null;
            if (!$product) {
                throw new \RuntimeException('Invalid product selected.');
            }

            $masterUnitPrice = max(0, (int) ($product->product_price ?? 0));

            $requestedUnitPrice = array_key_exists('original_price', $row) && $row['original_price'] !== null
                ? max(0, (int) $row['original_price'])
                : $masterUnitPrice;

            $netPrice = array_key_exists('price', $row) && $row['price'] !== null
                ? max(0, (int) $row['price'])
                : $requestedUnitPrice;

            $unitPrice = max($requestedUnitPrice, $netPrice, $masterUnitPrice);
            $discountType = (string) ($row['product_discount_type'] ?? 'fixed') === 'percentage'
                ? 'percentage'
                : 'fixed';

            if ($discountType === 'percentage') {
                $discountValue = array_key_exists('discount_value', $row)
                    ? (float) $row['discount_value']
                    : 0.0;

                if ($discountValue < 0 || $discountValue > 100) {
                    throw new \RuntimeException('Item discount percentage cannot exceed 100%.');
                }

                $itemDiscountAmount = (int) round($unitPrice * ($discountValue / 100));
                $netPrice = max(0, $unitPrice - $itemDiscountAmount);
            } else {
                $nominalDiscount = array_key_exists('discount_value', $row) && $row['discount_value'] !== null
                    ? max(0, (int) $row['discount_value'])
                    : max(0, $unitPrice - $netPrice);

                if ($nominalDiscount > $unitPrice) {
                    throw new \RuntimeException('Item discount amount cannot be greater than unit price.');
                }

                $itemDiscountAmount = $nominalDiscount;
                $netPrice = max(0, $unitPrice - $itemDiscountAmount);
            }

            $subTotal = (int) ($qty * $netPrice);
            [$installationType, $customerVehicleId] = $this->resolveSaleOrderItemInstallationMetadata(
                $row,
                (int) request()->input('customer_id', 0),
                (int) (BranchContext::id() ?? 0)
            );

            $items[] = [
                'product_id' => $pid,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'price' => $netPrice,
                'product_discount_amount' => $itemDiscountAmount,
                'product_discount_type' => $discountType,
                'sub_total' => $subTotal,
                'installation_type' => $installationType,
                'customer_vehicle_id' => $customerVehicleId,
            ];

            if (!isset($qtyByProduct[$pid])) {
                $qtyByProduct[$pid] = 0;
            }

            $qtyByProduct[$pid] += $qty;
            $sellSubtotal += $subTotal;
            $masterSubtotal += ($qty * $masterUnitPrice);
        }

        return [
            'items' => $items,
            'qty_by_product' => $qtyByProduct,
            'sell_subtotal' => (int) $sellSubtotal,
            'master_subtotal' => (int) $masterSubtotal,
        ];
    }

    private function normalizeSaleOrderItemInstallationType($value): string
    {
        return (string) $value === 'with_installation' ? 'with_installation' : 'item_only';
    }

    private function resolveSaleOrderItemInstallationMetadata(array $row, int $customerId, int $branchId): array
    {
        $installationType = $this->normalizeSaleOrderItemInstallationType($row['installation_type'] ?? 'item_only');

        if ($installationType !== 'with_installation') {
            return ['item_only', null];
        }

        $vehicleId = (int) ($row['customer_vehicle_id'] ?? 0);
        if ($customerId <= 0 || $vehicleId <= 0) {
            throw new \RuntimeException('Vehicle is required for Sale Order items with installation.');
        }

        $vehicleExists = CustomerVehicle::query()
            ->where('id', $vehicleId)
            ->where('customer_id', $customerId)
            ->when($branchId > 0, function ($query) use ($branchId) {
                $query->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->exists();

        if (!$vehicleExists) {
            throw new \RuntimeException('Selected vehicle does not belong to the selected Sale Order customer.');
        }

        return ['with_installation', $vehicleId];
    }

    private function resolveHeaderDiscount(Request $request, int $itemsSubtotal, int $taxAmount, int $shipping, int $fee): array
    {
        $discountType = (string) ($request->discount_type ?? 'percentage') === 'fixed' ? 'fixed' : 'percentage';
        $rawValue = $request->header_discount_value ?? $request->discount_percentage ?? 0;
        $discountValue = is_numeric($rawValue) ? (float) $rawValue : 0.0;

        if ($discountValue < 0) {
            throw new \RuntimeException('Discount cannot be negative.');
        }

        if ($discountType === 'percentage' && $discountValue > 100) {
            throw new \RuntimeException('Discount percentage cannot exceed 100%.');
        }

        $baseBeforeDiscount = max(0, $itemsSubtotal + $taxAmount + $shipping + $fee);

        if ($discountType === 'fixed') {
            $discountAmount = (int) round($discountValue);

            if ($discountAmount > $baseBeforeDiscount) {
                throw new \RuntimeException('Discount amount cannot be greater than total before discount.');
            }

            $discountPercentage = $itemsSubtotal > 0
                ? round(($discountAmount / $itemsSubtotal) * 100, 2)
                : 0.0;
        } else {
            $discountPercentage = round($discountValue, 2);
            $discountAmount = (int) round($itemsSubtotal * ($discountPercentage / 100));
        }

        $grandTotal = (int) round($itemsSubtotal + $taxAmount + $shipping + $fee - $discountAmount);

        if ($grandTotal < 0) {
            throw new \RuntimeException('Grand Total cannot be negative.');
        }

        return [
            'percentage' => (float) $discountPercentage,
            'amount' => (int) $discountAmount,
            'grand_total' => (int) $grandTotal,
        ];
    }

    public function index(SaleOrdersDataTable $dataTable)
    {
        abort_if(Gate::denies('access_sale_orders'), 403);
        return $dataTable->render('saleorder::index');
    }

    public function create(Request $request)
    {
        abort_if(Gate::denies('create_sale_orders'), 403);

        try {
            if ($request->filled('branch_id')) {
                BranchContext::set($request->get('branch_id'));
            }

            // ✅ Block jika active_branch = "all" / kosong
            $active = session('active_branch');
            if ($active === 'all' || empty($active)) {
                throw new \RuntimeException("Please choose a specific branch first (not 'All Branch').");
            }

            $branchId = BranchContext::id();

            $customers = Customer::query()
                ->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
                })
                ->orderBy('customer_name')
                ->get();

            $warehouses = Warehouse::query()
                ->where('branch_id', $branchId)
                ->orderBy('warehouse_name')
                ->get();

            $source = (string) $request->get('source', 'manual');
            abort_unless(in_array($source, ['manual', 'quotation', 'sale', 'lead'], true), 403);

            $prefillCustomerId = null;
            $prefillWarehouseId = null; // tetap nullable
            $prefillDate = date('Y-m-d');
            $prefillNote = null;
            $prefillItems = [];
            $prefillRefText = null;

            if ($source === 'quotation') {
                $quotationId = (int) $request->get('quotation_id', 0);
                if ($quotationId <= 0) abort(422, 'quotation_id is required');

                $quotation = Quotation::query()
                    ->where('id', $quotationId)
                    ->where('branch_id', $branchId)
                    ->with(['quotationDetails'])
                    ->firstOrFail();

                $prefillCustomerId = (int) $quotation->customer_id;
                $prefillDate = (string) $quotation->getRawOriginal('date');
                $prefillNote = 'Created from Quotation #' . ($quotation->reference ?? $quotation->id);
                $prefillRefText = 'Quotation: ' . ($quotation->reference ?? $quotation->id);

                foreach ($quotation->quotationDetails as $d) {
                    $unitPrice = (int) ($d->price ?? 0);
                    $qty = (int) ($d->quantity ?? 0);

                    $prefillItems[] = [
                        'product_id' => (int) $d->product_id,
                        'quantity'   => $qty,
                        'price'      => $unitPrice,
                        'original_price' => $unitPrice,
                        'unit_price' => $unitPrice,
                        'product_discount_amount' => 0,
                        'product_discount_type' => 'fixed',
                        'sub_total' => (int) ($qty * $unitPrice),
                        'installation_type' => 'item_only',
                        'customer_vehicle_id' => null,

                        // ✅ nanti di-inject dari master product
                        'product_name' => null,
                        'product_code' => null,
                    ];
                }
            }

            if ($source === 'sale') {
                $saleId = (int) $request->get('sale_id', 0);
                if ($saleId <= 0) abort(422, 'sale_id is required');

                $sale = Sale::query()
                    ->where('id', $saleId)
                    ->where('branch_id', $branchId)
                    ->with(['saleDetails'])
                    ->firstOrFail();

                $prefillCustomerId = (int) $sale->customer_id;
                $prefillDate = (string) $sale->getRawOriginal('date');
                $prefillNote = 'Created from Invoice #' . ($sale->reference ?? $sale->id);
                $prefillRefText = 'Invoice: ' . ($sale->reference ?? $sale->id);

                foreach ($sale->saleDetails as $d) {
                    $unitPrice = $d->unit_price !== null ? (int) $d->unit_price : (int) ($d->price ?? 0);
                    $netPrice = (int) ($d->price ?? $unitPrice);
                    $qty = (int) ($d->quantity ?? 0);
                    $itemDiscount = (int) ($d->product_discount_amount ?? max(0, $unitPrice - $netPrice));

                    $prefillItems[] = [
                        'product_id' => (int) $d->product_id,
                        'quantity'   => $qty,
                        'price'      => $netPrice,
                        'original_price' => $unitPrice,
                        'unit_price' => $unitPrice,
                        'product_discount_amount' => $itemDiscount,
                        'product_discount_type' => (string) ($d->product_discount_type ?? 'fixed'),
                        'sub_total' => (int) ($d->sub_total ?? ($qty * $netPrice)),
                        'installation_type' => $this->normalizeSaleOrderItemInstallationType($d->installation_type ?? 'item_only'),
                        'customer_vehicle_id' => $this->normalizeSaleOrderItemInstallationType($d->installation_type ?? 'item_only') === 'with_installation'
                            ? $d->customer_vehicle_id
                            : null,

                        // ✅ nanti di-inject dari master product
                        'product_name' => null,
                        'product_code' => null,
                    ];
                }

                // kalau invoice lama punya warehouse per item & cuma 1 warehouse, keep (opsional)
                $whIds = $sale->saleDetails->pluck('warehouse_id')->filter()->unique()->values();
                if ($whIds->count() === 1) {
                    $prefillWarehouseId = (int) $whIds->first();
                }
            }

            if ($source === 'lead') {
                $leadId = (int) $request->get('lead_id', 0);
                if ($leadId <= 0) abort(422, 'lead_id is required');

                $lead = Lead::query()
                    ->where('id', $leadId)
                    ->where('branch_id', $branchId)
                    ->with(['product', 'leadProducts'])
                    ->firstOrFail();

                $prefillCustomerId = $lead->customer_id ? (int) $lead->customer_id : null;
                $prefillDate = date('Y-m-d');
                $leadLabel = '#' . $lead->id . ($lead->ref_code ? ' / Ref: ' . $lead->ref_code : '');
                $prefillNote = trim('Created from CRM Lead ' . $leadLabel . "\n" . (string) ($lead->notes ?? ''));
                $prefillRefText = 'CRM Lead: ' . $leadLabel;

                $leadItems = $lead->leadProducts->isNotEmpty()
                    ? $lead->leadProducts
                    : collect($lead->product_id ? [[
                        'product_id' => (int) $lead->product_id,
                        'quantity' => 1,
                        'unit_price' => (int) ($lead->estimated_price ?: ($lead->product?->product_price ?? 0)),
                        'product_name' => $lead->product?->product_name ?: $lead->product_name,
                        'product_code' => $lead->product?->product_code ?: $lead->product_code,
                    ]] : []);

                foreach ($leadItems as $item) {
                    $unitPrice = (int) (is_array($item) ? $item['unit_price'] : $item->unit_price);
                    $quantity = max((int) (is_array($item) ? $item['quantity'] : $item->quantity), 1);
                    $prefillItems[] = [
                        'product_id' => (int) (is_array($item) ? $item['product_id'] : $item->product_id),
                        'quantity'   => $quantity,
                        'price'      => $unitPrice,
                        'original_price' => $unitPrice,
                        'unit_price' => $unitPrice,
                        'product_discount_amount' => 0,
                        'product_discount_type' => 'fixed',
                        'sub_total' => $unitPrice * $quantity,
                        'product_name' => is_array($item) ? $item['product_name'] : $item->product_name,
                        'product_code' => is_array($item) ? $item['product_code'] : $item->product_code,
                        'installation_type' => 'item_only',
                        'customer_vehicle_id' => null,
                    ];
                }
            }

            // ✅ Master product untuk dropdown + untuk inject label ke prefillItems
            $products = Product::query()
                ->select('id', 'product_name', 'product_code')
                ->orderBy('product_name')
                ->limit(500)
                ->get();

            // ✅ inject product_name + product_code ke prefillItems (biar Livewire ga fallback "Product #id")
            if (!empty($prefillItems)) {
                $neededIds = collect($prefillItems)->pluck('product_id')->filter()->unique()->values();
                if ($neededIds->count() > 0) {
                    $map = Product::query()
                        ->select('id', 'product_name', 'product_code')
                        ->whereIn('id', $neededIds->all())
                        ->get()
                        ->keyBy('id');

                    foreach ($prefillItems as $i => $row) {
                        $pid = (int) ($row['product_id'] ?? 0);
                        $p = $pid > 0 ? ($map[$pid] ?? null) : null;

                        $prefillItems[$i]['product_name'] = $p?->product_name;
                        $prefillItems[$i]['product_code'] = $p?->product_code;
                    }
                }
            }

            return view('saleorder::create', compact(
                'customers',
                'warehouses',
                'products',
                'source',
                'prefillCustomerId',
                'prefillWarehouseId',
                'prefillDate',
                'prefillNote',
                'prefillItems',
                'prefillRefText'
            ));
        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_sale_orders'), 403);

        try {
            if ($request->filled('branch_id')) {
                BranchContext::set($request->get('branch_id'));
            }

            $branchId = BranchContext::id();

            $source = (string) $request->get('source', 'manual');
            abort_unless(in_array($source, ['manual', 'quotation', 'sale', 'lead'], true), 403);

            $rules = [
                'date' => 'required|date',
                'customer_id' => 'required|integer',

                'warehouse_id' => 'nullable|integer',
                'note' => 'nullable|string|max:5000',

                'tax_percentage' => 'required|numeric|min:0|max:100',
                'discount_type' => 'required|in:fixed,percentage',
                'header_discount_value' => 'required|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',

                'shipping_amount' => 'required|integer|min:0',
                'fee_amount' => 'required|integer|min:0',

                // ✅ allow decimal deposit %
                'deposit_percentage' => 'nullable|numeric|min:0|max:100',
                'deposit_amount' => 'nullable|integer|min:0',
                'deposit_payment_method' => 'nullable|string|max:255',
                'deposit_code' => 'nullable|string|max:255',

                // ✅ NEW: DP Received
                'deposit_received_amount' => 'nullable|integer|min:0',
                'deposit_received_use_max' => 'nullable|in:1',

                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'nullable|integer|min:0',
                'items.*.original_price' => 'nullable|integer|min:0',
                'items.*.unit_price' => 'nullable|integer|min:0',
                'items.*.discount_value' => 'nullable|numeric|min:0',
                'items.*.product_discount_amount' => 'nullable|integer|min:0',
                'items.*.product_discount_type' => 'nullable|in:fixed,percentage',
                'items.*.sub_total' => 'nullable|integer|min:0',
                'items.*.installation_type' => 'nullable|in:item_only,with_installation',
                'items.*.customer_vehicle_id' => 'nullable|integer',
            ];

            if ($source === 'quotation') $rules['quotation_id'] = 'required|integer';
            if ($source === 'sale') $rules['sale_id'] = 'required|integer';
            if ($source === 'lead') $rules['lead_id'] = 'required|integer';

            $request->validate($rules);

            $saleOrderId = null;

            DB::transaction(function () use ($request, $branchId, $source, &$saleOrderId) {

                $customer = Customer::query()
                    ->where('id', (int) $request->customer_id)
                    ->where(function ($q) use ($branchId) {
                        $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    })
                    ->firstOrFail();

                $quotationId = null;
                $saleId = null;
                $lead = null;

                if ($source === 'quotation') {
                    $quotationId = (int) $request->quotation_id;

                    Quotation::query()
                        ->where('id', $quotationId)
                        ->where('branch_id', $branchId)
                        ->firstOrFail();

                    $exists = SaleOrder::query()
                        ->where('branch_id', $branchId)
                        ->where('quotation_id', $quotationId)
                        ->exists();

                    if ($exists) abort(422, 'Sale Order for this quotation already exists.');
                }

                if ($source === 'sale') {
                    $saleId = (int) $request->sale_id;

                    Sale::query()
                        ->where('id', $saleId)
                        ->where('branch_id', $branchId)
                        ->firstOrFail();

                    $exists = SaleOrder::query()
                        ->where('branch_id', $branchId)
                        ->where('sale_id', $saleId)
                        ->exists();

                    if ($exists) abort(422, 'Sale Order for this invoice already exists.');
                }

                if ($source === 'lead') {
                    $lead = Lead::query()
                        ->where('id', (int) $request->lead_id)
                        ->where('branch_id', $branchId)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                $productIds = collect($request->items)
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                if (empty($productIds)) abort(422, 'Items are empty.');

                $productMap = Product::query()
                    ->select('id', 'product_price')
                    ->whereIn('id', $productIds)
                    ->get()
                    ->keyBy('id');

                if ($productMap->count() !== count($productIds)) {
                    abort(422, 'Invalid product selected.');
                }

                $itemSnapshot = $this->buildSaleOrderItemSnapshots((array) $request->items, $productMap);
                $normalizedItems = $itemSnapshot['items'];
                $qtyByProduct = $itemSnapshot['qty_by_product'];
                $sellSubtotal = (int) $itemSnapshot['sell_subtotal'];

                if (empty($qtyByProduct)) abort(422, 'Items are empty.');

                // totals
                $taxPct = (float) $request->tax_percentage;
                $shipping = (int) $request->shipping_amount;
                $fee = (int) $request->fee_amount;

                $taxAmount = (int) round($sellSubtotal * ($taxPct / 100));
                $headerDiscount = $this->resolveHeaderDiscount($request, $sellSubtotal, $taxAmount, $shipping, $fee);
                $discountPct = (float) $headerDiscount['percentage'];
                $discountAmount = (int) $headerDiscount['amount'];
                $grandTotal = (int) $headerDiscount['grand_total'];

                // ==========================
                // Deposit planned (max)
                // ==========================
                $depositPct = (float) ($request->deposit_percentage ?? 0);
                $depositPct = max(0, min(100, $depositPct));

                $depositAmountInput = $request->deposit_amount;
                $depositAmount = (int) (is_numeric($depositAmountInput) ? $depositAmountInput : 0);

                if ($depositAmount <= 0 && $depositPct > 0) {
                    $depositAmount = (int) round($grandTotal * ($depositPct / 100));
                }

                if ($depositAmount < 0) $depositAmount = 0;
                if ($depositAmount > $grandTotal) abort(422, 'Deposit amount cannot be greater than Grand Total.');

                // ==========================
                // ✅ Deposit received (actual)
                // ==========================
                $useMaxReceived = (string)$request->deposit_received_use_max === '1';
                $receivedInput  = $request->deposit_received_amount;
                $depositReceived = (int) (is_numeric($receivedInput) ? $receivedInput : 0);

                if ($depositReceived < 0) $depositReceived = 0;

                // kalau Use Max ON -> received = planned
                if ($useMaxReceived) {
                    $depositReceived = (int) $depositAmount;
                }

                // kalau user isi received tapi planned masih 0, kita naikin planned biar konsisten
                if ($depositReceived > 0 && $depositAmount <= 0) {
                    $depositAmount = $depositReceived;
                }

                if ($depositAmount > $grandTotal) abort(422, 'Deposit amount cannot be greater than Grand Total.');
                if ($depositReceived > $grandTotal) abort(422, 'DP Received cannot be greater than Grand Total.');
                if ($depositReceived > $depositAmount) abort(422, 'DP Received cannot be greater than Deposit Amount.');

                $depositCode = $request->deposit_code ? (string) $request->deposit_code : null;
                $depositPaymentMethod = $request->deposit_payment_method ? (string) $request->deposit_payment_method : null;

                // wajib isi akun & method kalau planned>0 atau received>0
                if ($depositAmount > 0 || $depositReceived > 0) {
                    if (empty($depositCode)) abort(422, 'Deposit To is required when DP (planned/received) > 0.');
                    if (empty($depositPaymentMethod)) abort(422, 'Deposit Payment Method is required when DP (planned/received) > 0.');
                }

                $so = SaleOrder::create([
                    'branch_id' => $branchId,
                    'customer_id' => (int) $customer->id,
                    'quotation_id' => $quotationId,
                    'sale_id' => $saleId,
                    'warehouse_id' => null,
                    'date' => $request->date,
                    'status' => 'pending',
                    'note' => $request->note,

                    'tax_percentage' => $taxPct,
                    'tax_amount' => $taxAmount,

                    'discount_percentage' => (float) $discountPct,
                    'discount_amount' => (int) $discountAmount,

                    'shipping_amount' => $shipping,
                    'fee_amount' => $fee,
                    'subtotal_amount' => (int) $sellSubtotal,
                    'total_amount' => (int) $grandTotal,

                    // dp planned
                    'deposit_percentage' => (float) $depositPct,
                    'deposit_amount' => (int) $depositAmount,
                    'deposit_payment_method' => $depositPaymentMethod,
                    'deposit_code' => $depositCode,

                    // ✅ dp received
                    'deposit_received_amount' => (int) $depositReceived,
                ]);

                $saleOrderId = (int) $so->id;

                foreach ($normalizedItems as $row) {
                    SaleOrderItem::create([
                        'sale_order_id' => $so->id,
                        'product_id' => (int) $row['product_id'],
                        'quantity' => (int) $row['quantity'],
                        'unit_price' => (int) $row['unit_price'],
                        'price' => (int) $row['price'],
                        'product_discount_amount' => (int) $row['product_discount_amount'],
                        'product_discount_type' => (string) ($row['product_discount_type'] ?? 'fixed'),
                        'sub_total' => (int) $row['sub_total'],
                        'installation_type' => (string) ($row['installation_type'] ?? 'item_only'),
                        'customer_vehicle_id' => $row['customer_vehicle_id'] ?? null,
                    ]);
                }

                if ($lead) {
                    $lead->forceFill([
                        'sale_order_id' => (int) $so->id,
                        'status' => 'deal',
                    ])->save();
                }

                // reserve stock (existing)
                foreach ($qtyByProduct as $pid => $qty) {
                    $existing = DB::table('stocks')
                        ->where('branch_id', (int) $branchId)
                        ->whereNull('warehouse_id')
                        ->where('product_id', (int) $pid)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        DB::table('stocks')
                            ->where('id', (int) $existing->id)
                            ->update([
                                'qty_reserved' => (int) ($existing->qty_reserved ?? 0) + (int) $qty,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::table('stocks')->insert([
                            'branch_id'    => (int) $branchId,
                            'warehouse_id' => null,
                            'product_id'   => (int) $pid,
                            'qty_total' => 0,
                            'qty_reserved'  => (int) $qty,
                            'qty_incoming'  => 0,
                            'min_stock'     => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // ✅ DP payment record: pakai DP Received
                if ((int) $depositReceived > 0) {
                    $salePayment = \Modules\Sale\Entities\SalePayment::create([
                        'sale_id' => null,
                        'sale_order_id' => (int) $so->id,
                        'amount' => (int) $depositReceived,
                        'date' => (string) $request->date,
                        'reference' => 'SO/' . (string) ($so->reference ?? ('SO-' . $so->id)),
                        'payment_method' => (string) $depositPaymentMethod,
                        'note' => 'Deposit (DP) RECEIVED from Sale Order',
                        'deposit_code' => (string) $depositCode,
                    ]);

                    \App\Helpers\Helper::addNewTransaction([
                        'date' =>  (string) $request->date,
                        'label' => "Deposit For Sale Order #".$so->reference,
                        'description' => "Sale Order: ".$so->reference,
                        'purchase_id' => null,
                        'purchase_payment_id' => null,
                        'purchase_return_id' => null,
                        'purchase_return_payment_id' => null,
                        'sale_id' => null,
                        'sale_payment_id' => $salePayment->id,
                        'sale_return_id' => null,
                        'sale_return_payment_id' => null,
                    ], [
                        [
                            'subaccount_number' => (string) $depositCode,
                            'amount' => (int) $depositReceived,
                            'type' => 'debit'
                        ],
                        [
                            'subaccount_number' => '1-10100',
                            'amount' => (int) $depositReceived,
                            'type' => 'credit'
                        ],
                    ]);
                }

                $qid = (int) ($so->quotation_id ?? 0);
                if ($qid > 0) {
                    $this->markQuotationCompletedIfHasChildren($qid, (int) $branchId);
                }
            });

            toast('Sale Order Created!', 'success');
            return redirect()->route('sale-orders.show', $saleOrderId);

        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    public function show(SaleOrder $saleOrder)
    {
        abort_if(Gate::denies('show_sale_orders'), 403);

        $branchId = BranchContext::id();
        if ($branchId && (int) $saleOrder->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        $saleOrder->load([
            'customer',
            'warehouse',
            'items.product',
            'items.customerVehicle',
            'creator',
            'updater',
            'deliveries' => function ($q) {
                $q->orderBy('id', 'desc');
            },
            // ✅ FIX: eager load warehouse relation inside deliveries
            'deliveries.warehouse',
        ]);

        // ✅ EXTRA safety: kalau warehouse_id ada tapi relasi belum kebaca, set manual
        foreach (($saleOrder->deliveries ?? []) as $d) {
            $wid = (int) ($d->warehouse_id ?? 0);
            if ($wid > 0 && !$d->relationLoaded('warehouse')) {
                $wh = \Modules\Product\Entities\Warehouse::find($wid);
                if ($wh) $d->setRelation('warehouse', $wh);
            }
            if ($wid > 0 && $d->relationLoaded('warehouse') && empty($d->warehouse)) {
                $wh = \Modules\Product\Entities\Warehouse::find($wid);
                if ($wh) $d->setRelation('warehouse', $wh);
            }
        }

        $remainingMap = $this->getRemainingQtyBySaleOrder($saleOrder->id);
        $plannedRemainingMap = $this->getPlannedRemainingQtyBySaleOrder($saleOrder->id);

        return view('saleorder::show', compact(
            'saleOrder',
            'remainingMap',
            'plannedRemainingMap'
        ));
    }

    public function print(SaleOrder $saleOrder)
    {
        abort_if(Gate::denies('show_sale_orders'), 403);

        $branchId = BranchContext::id();
        if ($branchId && (int) $saleOrder->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        return $this->buildSaleOrderPdf($saleOrder)
            ->stream($this->saleOrderPdfFilename($saleOrder));
    }

    public function download(SaleOrder $saleOrder)
    {
        abort_if(Gate::denies('show_sale_orders'), 403);

        $branchId = BranchContext::id();
        if ((int) $saleOrder->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        return $this->buildSaleOrderPdf($saleOrder)
            ->download($this->saleOrderPdfFilename($saleOrder));
    }

    public function dpReceipt(\Modules\SaleOrder\Entities\SaleOrder $saleOrder)
    {
        abort_if(\Illuminate\Support\Facades\Gate::denies('show_sale_orders'), 403);

        $branchId = \App\Support\BranchContext::id();
        if ($branchId && (int) $saleOrder->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        $saleOrder->load(['customer']);
        $branch = !empty($saleOrder->branch_id)
            ? Branch::withoutGlobalScopes()->find((int) $saleOrder->branch_id)
            : null;

        $dpReceived = (int) ($saleOrder->deposit_received_amount ?? 0);
        if ($dpReceived <= 0) {
            abort(404, 'This Sale Order has no DP received.');
        }

        // Ambil payment DP terakhir untuk SO ini (yang dibuat saat store)
        $salePayment = \Modules\Sale\Entities\SalePayment::query()
            ->whereNull('sale_id')
            ->where('sale_order_id', (int) $saleOrder->id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$salePayment) {
            // Safety: kalau ternyata record payment tidak ada (data lama), tetap bisa print pakai data SO
            $salePayment = new \Modules\Sale\Entities\SalePayment();
            $salePayment->id = 0;
            $salePayment->sale_order_id = (int) $saleOrder->id;
            $salePayment->sale_id = null;
            $salePayment->amount = $dpReceived;
            $salePayment->date = $saleOrder->date ?? date('Y-m-d');
            $salePayment->reference = 'SO/' . (string) ($saleOrder->reference ?? ('SO-' . $saleOrder->id));
            $salePayment->payment_method = $saleOrder->deposit_payment_method ?? '-';
            $salePayment->note = 'Deposit (DP) Received';
            $salePayment->deposit_code = $saleOrder->deposit_code ?? null;
        }

        // paid before receipt (kalau suatu saat DP bisa lebih dari 1 kali)
        $paidBefore = (int) \Modules\Sale\Entities\SalePayment::query()
            ->whereNull('sale_id')
            ->where('sale_order_id', (int) $saleOrder->id)
            ->where('id', '<', (int) ($salePayment->id ?? 0))
            ->sum('amount');

        $paidAfter = $paidBefore + (int) ($salePayment->amount ?? 0);

        $grandTotal = (int) ($saleOrder->total_amount ?? 0);
        $remaining = max(0, $grandTotal - $paidAfter);

        // PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('saleorder::dp-receipt', [
            'saleOrder' => $saleOrder,
            'customer' => $saleOrder->customer,
            'salePayment' => $salePayment,
            'paidBefore' => $paidBefore,
            'paidAfter' => $paidAfter,
            'remaining' => $remaining,
            'branch' => $branch,
        ])->setPaper('a4');

        $ref = $salePayment->reference ?? ('SO-' . $saleOrder->id);
        return $pdf->stream('dp-receipt-' . $ref . '.pdf');
    }

    public function dpReceiptDebug(\Modules\SaleOrder\Entities\SaleOrder $saleOrder)
    {
        abort_if(\Illuminate\Support\Facades\Gate::denies('show_sale_orders'), 403);

        $branchId = \App\Support\BranchContext::id();
        if ((int) $saleOrder->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        $saleOrder->load(['customer']);

        $dpReceived = (int) ($saleOrder->deposit_received_amount ?? 0);
        if ($dpReceived <= 0) {
            abort(404, 'This Sale Order has no DP received.');
        }

        $salePayment = \Modules\Sale\Entities\SalePayment::query()
            ->whereNull('sale_id')
            ->where('sale_order_id', (int) $saleOrder->id)
            ->orderBy('id', 'desc')
            ->first();

        if (!$salePayment) {
            $salePayment = new \Modules\Sale\Entities\SalePayment();
            $salePayment->id = 0;
            $salePayment->sale_order_id = (int) $saleOrder->id;
            $salePayment->sale_id = null;
            $salePayment->amount = $dpReceived;
            $salePayment->date = $saleOrder->date ?? date('Y-m-d');
            $salePayment->reference = 'SO/' . (string) ($saleOrder->reference ?? ('SO-' . $saleOrder->id));
            $salePayment->payment_method = $saleOrder->deposit_payment_method ?? '-';
            $salePayment->note = 'Deposit (DP) Received';
            $salePayment->deposit_code = $saleOrder->deposit_code ?? null;
        }

        $paidBefore = (int) \Modules\Sale\Entities\SalePayment::query()
            ->whereNull('sale_id')
            ->where('sale_order_id', (int) $saleOrder->id)
            ->where('id', '<', (int) ($salePayment->id ?? 0))
            ->sum('amount');

        $paidAfter = $paidBefore + (int) ($salePayment->amount ?? 0);

        $grandTotal = (int) ($saleOrder->total_amount ?? 0);
        $remaining = max(0, $grandTotal - $paidAfter);

        return view('saleorder::dp-receipt', [
            'saleOrder' => $saleOrder,
            'customer' => $saleOrder->customer,
            'salePayment' => $salePayment,
            'paidBefore' => $paidBefore,
            'paidAfter' => $paidAfter,
            'remaining' => $remaining,
        ]);
    }

    // =========================
    // ✅ NEW: EDIT SALE ORDER
    // =========================
    public function edit(SaleOrder $saleOrder)
    {
        abort_if(Gate::denies('edit_sale_orders'), 403);

        try {
            $branchId = BranchContext::id();

            if ((int) $saleOrder->branch_id !== (int) $branchId) {
                throw new \RuntimeException('Wrong branch context.');
            }

            $st = strtolower((string) ($saleOrder->status ?? 'pending'));
            if ($st !== 'pending') {
                throw new \RuntimeException('Only pending Sale Order can be edited.');
            }

            $saleOrder->load(['items.customerVehicle']);

            $customers = Customer::query()
                ->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')
                      ->orWhere('branch_id', $branchId);
                })
                ->orderBy('customer_name')
                ->get();

            $products = Product::query()
                ->orderBy('product_name')
                ->limit(500)
                ->get();

            // convert items to prefill format for Livewire
            $items = $saleOrder->items->map(function ($it) {
                $unitPrice = $it->unit_price !== null ? (int) $it->unit_price : (int) ($it->price ?? 0);
                $netPrice = $it->price !== null ? (int) $it->price : $unitPrice;
                $qty = (int) ($it->quantity ?? 0);

                return [
                    'product_id' => (int) $it->product_id,
                    'quantity'   => $qty,
                    'price'      => $netPrice,
                    'original_price' => $unitPrice,
                    'unit_price' => $unitPrice,
                    'product_discount_amount' => (int) ($it->product_discount_amount ?? max(0, $unitPrice - $netPrice)),
                    'product_discount_type' => (string) ($it->product_discount_type ?? 'fixed'),
                    'sub_total' => (int) ($it->sub_total ?? ($qty * $netPrice)),
                    'installation_type' => $this->normalizeSaleOrderItemInstallationType($it->installation_type ?? 'item_only'),
                    'customer_vehicle_id' => $this->normalizeSaleOrderItemInstallationType($it->installation_type ?? 'item_only') === 'with_installation'
                        ? $it->customer_vehicle_id
                        : null,
                ];
            })->values()->toArray();

            return view('saleorder::edit', compact(
                'saleOrder',
                'customers',
                'products',
                'items'
            ));
        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    public function update(Request $request, SaleOrder $saleOrder)
    {
        abort_if(Gate::denies('edit_sale_orders'), 403);

        try {
            $branchId = BranchContext::id();

            if ((int) $saleOrder->branch_id !== (int) $branchId) {
                throw new \RuntimeException('Wrong branch context.');
            }

            $st = strtolower((string) ($saleOrder->status ?? 'pending'));
            if ($st !== 'pending') {
                throw new \RuntimeException('Only pending Sale Order can be edited.');
            }

            $request->validate([
                'date' => 'required|date',
                'customer_id' => 'required|integer',
                'note' => 'nullable|string|max:5000',

                'tax_percentage' => 'required|numeric|min:0|max:100',
                'discount_type' => 'required|in:fixed,percentage',
                'header_discount_value' => 'required|numeric|min:0',
                'discount_percentage' => 'nullable|numeric|min:0|max:100',

                'shipping_amount' => 'required|integer|min:0',
                'fee_amount' => 'required|integer|min:0',

                'deposit_percentage' => 'nullable|numeric|min:0|max:100',
                'deposit_amount' => 'nullable|integer|min:0',
                'deposit_payment_method' => 'nullable|string|max:255',
                'deposit_code' => 'nullable|string|max:255',

                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'nullable|integer|min:0',
                'items.*.original_price' => 'nullable|integer|min:0',
                'items.*.unit_price' => 'nullable|integer|min:0',
                'items.*.discount_value' => 'nullable|numeric|min:0',
                'items.*.product_discount_amount' => 'nullable|integer|min:0',
                'items.*.product_discount_type' => 'nullable|in:fixed,percentage',
                'items.*.sub_total' => 'nullable|integer|min:0',
                'items.*.installation_type' => 'nullable|in:item_only,with_installation',
                'items.*.customer_vehicle_id' => 'nullable|integer',
            ]);

            DB::transaction(function () use ($request, $saleOrder, $branchId) {

                $saleOrder = SaleOrder::query()
                    ->lockForUpdate()
                    ->with(['items'])
                    ->findOrFail((int) $saleOrder->id);

                $st = strtolower((string) ($saleOrder->status ?? 'pending'));
                if ($st !== 'pending') {
                    throw new \RuntimeException('Only pending Sale Order can be edited.');
                }

                $customer = Customer::query()
                    ->where('id', (int) $request->customer_id)
                    ->where(function ($q) use ($branchId) {
                        $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    })
                    ->firstOrFail();

                $productIds = collect($request->items)
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();

                if (empty($productIds)) {
                    throw new \RuntimeException('Items are empty.');
                }

                $productMap = Product::query()
                    ->select('id', 'product_price')
                    ->whereIn('id', $productIds)
                    ->get()
                    ->keyBy('id');

                if ($productMap->count() !== count($productIds)) {
                    throw new \RuntimeException('Invalid product selected.');
                }

                $itemSnapshot = $this->buildSaleOrderItemSnapshots((array) $request->items, $productMap);
                $normalizedItems = $itemSnapshot['items'];
                $qtyByProduct = $itemSnapshot['qty_by_product'];
                $sellSubtotal = (int) $itemSnapshot['sell_subtotal'];

                if (empty($qtyByProduct)) {
                    throw new \RuntimeException('Items are empty.');
                }

                $taxPct = (float) $request->tax_percentage;
                $shipping = (int) $request->shipping_amount;
                $fee = (int) $request->fee_amount;

                $taxAmount = (int) round($sellSubtotal * ($taxPct / 100));
                $headerDiscount = $this->resolveHeaderDiscount($request, $sellSubtotal, $taxAmount, $shipping, $fee);
                $discountPct = (float) $headerDiscount['percentage'];
                $discountAmount = (int) $headerDiscount['amount'];
                $grandTotal = (int) $headerDiscount['grand_total'];

                if ((int) ($saleOrder->deposit_amount ?? 0) > $grandTotal) {
                    throw new \RuntimeException('Existing Deposit amount cannot be greater than Grand Total.');
                }

                if ((int) ($saleOrder->deposit_received_amount ?? 0) > $grandTotal) {
                    throw new \RuntimeException('Existing DP Received cannot be greater than Grand Total.');
                }

                $saleOrder->update([
                    'date' => $request->date,
                    'customer_id' => (int) $customer->id,
                    'warehouse_id' => null,
                    'note' => $request->note,

                    'tax_percentage' => $taxPct,
                    'tax_amount' => $taxAmount,

                    // ✅ store computed truth
                    'discount_percentage' => (float) $discountPct,
                    'discount_amount' => (int) $discountAmount,

                    'shipping_amount' => $shipping,
                    'fee_amount' => $fee,
                    'subtotal_amount' => (int) $sellSubtotal,
                    'total_amount' => (int) $grandTotal,

                    'deposit_percentage' => (float) ($saleOrder->deposit_percentage ?? 0),
                    'deposit_amount' => (int) ($saleOrder->deposit_amount ?? 0),
                    'deposit_payment_method' => $saleOrder->deposit_payment_method,
                    'deposit_code' => $saleOrder->deposit_code,
                    'deposit_received_amount' => (int) ($saleOrder->deposit_received_amount ?? 0),
                ]);

                // replace items
                SaleOrderItem::query()
                    ->where('sale_order_id', (int) $saleOrder->id)
                    ->delete();

                foreach ($normalizedItems as $row) {
                    SaleOrderItem::create([
                        'sale_order_id' => (int) $saleOrder->id,
                        'product_id' => (int) $row['product_id'],
                        'quantity' => (int) $row['quantity'],
                        'unit_price' => (int) $row['unit_price'],
                        'price' => (int) $row['price'],
                        'product_discount_amount' => (int) $row['product_discount_amount'],
                        'product_discount_type' => (string) ($row['product_discount_type'] ?? 'fixed'),
                        'sub_total' => (int) $row['sub_total'],
                        'installation_type' => (string) ($row['installation_type'] ?? 'item_only'),
                        'customer_vehicle_id' => $row['customer_vehicle_id'] ?? null,
                    ]);
                }

                // DP payment record tetap tidak diubah di update (sesuai catatan kamu)
            });

            toast('Sale Order updated!', 'success');
            return redirect()->route('sale-orders.show', $saleOrder->id);

        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    public function destroy(SaleOrder $saleOrder)
    {
        abort_if(Gate::denies('delete_sale_orders'), 403);

        try {
            $branchId = BranchContext::id();

            if ((int) $saleOrder->branch_id !== (int) $branchId) {
                throw new \RuntimeException('Wrong branch context.');
            }

            $st = strtolower((string) ($saleOrder->status ?? 'pending'));
            if ($st !== 'pending') {
                throw new \RuntimeException('Only pending Sale Order can be deleted.');
            }

            // ✅ safety: kalau sudah ada Sale Delivery turunan AKTIF (belum soft delete), jangan boleh delete
            $hasDelivery = DB::table('sale_deliveries')
                ->where('branch_id', (int) $branchId)
                ->where('sale_order_id', (int) $saleOrder->id)
                ->whereNull('deleted_at') // 🔥 INI KUNCI supaya soft-deleted tidak dihitung
                ->exists();

            if ($hasDelivery) {
                throw new \RuntimeException('Cannot delete. This Sale Order already has Sale Deliveries.');
            }

            DB::transaction(function () use ($saleOrder, $branchId) {

                // ✅ lock SO + items
                $so = SaleOrder::query()
                    ->lockForUpdate()
                    ->with(['items'])
                    ->where('branch_id', (int) $branchId)
                    ->findOrFail((int) $saleOrder->id);

                $st = strtolower((string) ($so->status ?? 'pending'));
                if ($st !== 'pending') {
                    throw new \RuntimeException('Only pending Sale Order can be deleted.');
                }

                // ✅ build qtyByProduct dari items (yang masih aktif)
                $qtyByProduct = [];
                foreach (($so->items ?? []) as $it) {
                    $pid = (int) ($it->product_id ?? 0);
                    $qty = (int) ($it->quantity ?? 0);
                    if ($pid <= 0 || $qty <= 0) continue;

                    if (!isset($qtyByProduct[$pid])) $qtyByProduct[$pid] = 0;
                    $qtyByProduct[$pid] += $qty;
                }

                // ✅ rollback reserved pool stock (warehouse_id NULL)
                if (!empty($qtyByProduct)) {
                    foreach ($qtyByProduct as $pid => $qty) {
                        $pid = (int) $pid;
                        $qty = (int) $qty;
                        if ($pid <= 0 || $qty <= 0) continue;

                        $poolRow = DB::table('stocks')
                            ->where('branch_id', (int) $branchId)
                            ->whereNull('warehouse_id')
                            ->where('product_id', (int) $pid)
                            ->lockForUpdate()
                            ->first();

                        if ($poolRow) {
                            DB::table('stocks')
                                ->where('id', (int) $poolRow->id)
                                ->update([
                                    'qty_reserved' => DB::raw("GREATEST(COALESCE(qty_reserved,0) - {$qty}, 0)"),
                                    'updated_at'   => now(),
                                ]);
                        }
                        // kalau pool row tidak ada, ya skip (data lama / tidak konsisten), tapi aman.
                    }
                }

                // ✅ soft delete items dulu, lalu SO
                SaleOrderItem::query()
                    ->where('sale_order_id', (int) $so->id)
                    ->delete();

                $so->delete();
            });

            toast('Sale Order deleted!', 'warning');
            return redirect()->route('sale-orders.index');

        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    private function markQuotationCompletedIfHasChildren(int $quotationId, int $branchId): void
    {
        $hasSO = DB::table('sale_orders')
            ->where('branch_id', $branchId)
            ->where('quotation_id', $quotationId)
            ->exists();

        $hasSD = DB::table('sale_deliveries')
            ->where('branch_id', $branchId)
            ->where('quotation_id', $quotationId)
            ->exists();

        if ($hasSO || $hasSD) {
            DB::table('quotations')
                ->where('branch_id', $branchId)
                ->where('id', $quotationId)
                ->update([
                    'status' => 'Completed',
                    'updated_at' => now(),
                ]);
        }
    }

    private function getRemainingQtyBySaleOrder(int $saleOrderId): array
    {
        $deliveredExpr = $this->deliveredQtyExpr('sdi');

        $ordered = DB::table('sale_order_items')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->where('sale_order_id', $saleOrderId)
            ->whereNull('deleted_at')
            ->groupBy('product_id')
            ->get();

        $shipped = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_order_id', $saleOrderId)
            ->whereNull('sd.deleted_at')
            ->whereNull('sdi.deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('sd.confirmed_at')
                  ->orWhereIn(DB::raw('LOWER(sd.status)'), ['confirmed', 'partial']);
            })
            ->select(
                'sdi.product_id',
                DB::raw("SUM({$deliveredExpr}) as qty")
            )
            ->groupBy('sdi.product_id')
            ->get()
            ->keyBy('product_id');

        $remaining = [];

        foreach ($ordered as $row) {
            $pid = (int) $row->product_id;
            $orderedQty = (int) $row->qty;
            $shippedQty = isset($shipped[$pid]) ? (int) $shipped[$pid]->qty : 0;

            $rem = $orderedQty - $shippedQty;
            if ($rem < 0) $rem = 0;

            $remaining[$pid] = $rem;
        }

        return $remaining;
    }

    private function getPlannedRemainingQtyBySaleOrder(int $saleOrderId): array
    {
        $deliveredExpr = $this->deliveredQtyExpr('sdi');

        $ordered = DB::table('sale_order_items')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->where('sale_order_id', $saleOrderId)
            ->whereNull('deleted_at')
            ->groupBy('product_id')
            ->get();

        $delivered = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_order_id', $saleOrderId)
            ->whereNull('sd.deleted_at')
            ->whereNull('sdi.deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('sd.confirmed_at')
                  ->orWhereIn(DB::raw('LOWER(sd.status)'), ['confirmed', 'partial']);
            })
            ->select('sdi.product_id', DB::raw("SUM({$deliveredExpr}) as qty"))
            ->groupBy('sdi.product_id')
            ->get()
            ->keyBy('product_id');

        $plannedOutstanding = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_order_id', $saleOrderId)
            ->whereNull('sd.deleted_at')
            ->whereNull('sdi.deleted_at')
            ->whereIn(DB::raw('LOWER(sd.status)'), ['pending', 'partial'])
            ->select(
                'sdi.product_id',
                DB::raw("SUM(GREATEST(COALESCE(sdi.quantity,0) - CASE
                    WHEN LOWER(COALESCE(sd.status,'')) = 'partial' THEN {$deliveredExpr}
                    ELSE 0
                END, 0)) as qty")
            )
            ->groupBy('sdi.product_id')
            ->get()
            ->keyBy('product_id');

        $remaining = [];

        foreach ($ordered as $row) {
            $pid = (int) $row->product_id;
            $orderedQty = (int) $row->qty;
            $deliveredQty = isset($delivered[$pid]) ? (int) $delivered[$pid]->qty : 0;
            $plannedQty = isset($plannedOutstanding[$pid]) ? (int) $plannedOutstanding[$pid]->qty : 0;

            if ($deliveredQty < 0) $deliveredQty = 0;
            if ($plannedQty < 0) $plannedQty = 0;

            if ($deliveredQty > $orderedQty) $deliveredQty = $orderedQty;

            $maxPlannable = $orderedQty - $deliveredQty;
            if ($plannedQty > $maxPlannable) $plannedQty = $maxPlannable;

            $rem = $orderedQty - $deliveredQty - $plannedQty;
            if ($rem < 0) $rem = 0;

            $remaining[$pid] = $rem;
        }

        return $remaining;
    }
}
