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

class SaleDeliveryController extends Controller
{
    use SaleDeliveryShared;

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

        return view('saledelivery::show', compact('saleDelivery', 'mutations'));
    }

    public function create(Request $request)
    {
        abort_if(Gate::denies('create_sale_deliveries'), 403);

        try {
            $source = (string) $request->get('source', '');
            if (!in_array($source, ['quotation', 'sale', 'sale_order'], true)) {
                throw new \RuntimeException('Sale Delivery can only be created from Quotation, Sale, or Sale Order.');
            }

            if ($source === 'quotation' && !$request->filled('quotation_id')) throw new \RuntimeException('quotation_id is required');
            if ($source === 'sale' && !$request->filled('sale_id')) throw new \RuntimeException('sale_id is required');
            if ($source === 'sale_order' && !$request->filled('sale_order_id')) throw new \RuntimeException('sale_order_id is required');

            $branchId = BranchContext::id();

            $customers = Customer::query()
                ->forActiveBranch($branchId)
                ->orderBy('customer_name')
                ->get();

            $products = Product::query()->orderBy('product_name')->limit(500)->get();

            $prefillItems = [];
            $prefillCustomerId = null;
            $prefillSaleOrderRef = null;

            if ($source === 'sale_order') {
                $saleOrderId = (int) $request->sale_order_id;

                $saleOrder = SaleOrder::query()
                    ->where('id', $saleOrderId)
                    ->where('branch_id', $branchId)
                    ->with(['items'])
                    ->firstOrFail();

                $prefillSaleOrderRef = $saleOrder->reference ?? ('SO#' . $saleOrder->id);
                $prefillCustomerId = (int) $saleOrder->customer_id;

                $remainingMap = $this->getPlannedRemainingQtyBySaleOrder($saleOrderId);

                $hasAny = false;
                foreach ($remainingMap as $v) {
                    if ((int)$v > 0) { $hasAny = true; break; }
                }
                if (!$hasAny) {
                    throw new \RuntimeException('All items are already planned in existing deliveries (pending/confirmed/partial).');
                }

                foreach ($saleOrder->items as $it) {
                    $pid = (int) $it->product_id;
                    if ($pid <= 0) continue;

                    $rem = (int) ($remainingMap[$pid] ?? 0);
                    if ($rem <= 0) continue;

                    $prefillItems[] = [
                        'product_id' => $pid,
                        'quantity'   => $rem,
                        'price'      => (int) ($it->price ?? 0),
                    ];
                }
            }

            if ($source === 'sale') {
                $saleId = (int) $request->sale_id;

                $sale = DB::table('sales')
                    ->where('id', $saleId)
                    ->where('branch_id', $branchId)
                    ->first();

                if (!$sale) throw new \RuntimeException('Sale (invoice) not found in this branch.');

                $remainingMap = $this->getRemainingQtyBySale($saleId);

                $details = DB::table('sale_details')
                    ->where('sale_id', $saleId)
                    ->get();

                foreach ($details as $d) {
                    $pid = (int) $d->product_id;
                    if ($pid <= 0) continue;

                    $rem = (int) ($remainingMap[$pid] ?? 0);
                    if ($rem <= 0) continue;

                    $prefillItems[] = [
                        'product_id' => $pid,
                        'quantity'   => $rem,
                        'price'      => (int) ($d->price ?? 0),
                    ];
                }
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
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'nullable|integer|min:0',
            ];

            if ($source !== 'sale_order') $rules['customer_id'] = 'required|integer';
            if ($source === 'quotation') $rules['quotation_id'] = 'required|integer';
            if ($source === 'sale') $rules['sale_id'] = 'required|integer';
            if ($source === 'sale_order') $rules['sale_order_id'] = 'required|integer';

            $request->validate($rules);

            DB::transaction(function () use ($request, $branchId, $source) {

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

                    $remainingMap = $this->getPlannedRemainingQtyBySaleOrder($saleOrderId);

                    foreach ($request->items as $row) {
                        $pid = (int) $row['product_id'];
                        $qty = (int) $row['quantity'];
                        $rem = (int) ($remainingMap[$pid] ?? 0);

                        if ($qty > $rem) {
                            throw new \RuntimeException("Qty exceeds PLANNED remaining for product_id {$pid}. Remaining: {$rem}.");
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

                    $remainingMap = $this->getRemainingQtyBySale($saleId);

                    foreach ($request->items as $row) {
                        $pid = (int) $row['product_id'];
                        $qty = (int) $row['quantity'];
                        $rem = (int) ($remainingMap[$pid] ?? 0);

                        if ($qty > $rem) {
                            throw new \RuntimeException("Qty exceeds remaining for product_id {$pid}. Remaining: {$rem}.");
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

                foreach ($request->items as $row) {

                    // ✅ FIX: 0 / negatif dianggap "tidak diisi" => NULL
                    $price = array_key_exists('price', $row) && $row['price'] !== null
                        ? (int) $row['price']
                        : null;

                    if ($price !== null && $price <= 0) {
                        $price = null;
                    }

                    SaleDeliveryItem::create([
                        'sale_delivery_id' => (int) $saleDelivery->id,
                        'product_id'       => (int) $row['product_id'],
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

                // ✅ FIX: warehouse boleh null (karena flow kamu memang allow null)
                'warehouse_id' => 'nullable|integer',

                'note' => 'nullable|string|max:2000',
                'items' => 'required|array|min:1',
                'items.*.id' => 'nullable|integer',
                'items.*.product_id' => 'required|integer',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'nullable|integer|min:0',
            ]);

            DB::transaction(function () use ($request, $saleDelivery, $branchId) {

                $saleDelivery = SaleDelivery::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->with(['items'])
                    ->findOrFail($saleDelivery->id);

                // ✅ warehouse_id optional
                $warehouseId = null;
                if (!empty($request->warehouse_id)) {
                    $warehouse = Warehouse::query()
                        ->where('branch_id', $branchId)
                        ->where('id', (int) $request->warehouse_id)
                        ->firstOrFail();

                    $warehouseId = (int) $warehouse->id;
                }

                $saleDelivery->update([
                    'date' => $request->date,
                    'warehouse_id' => $warehouseId,   // ✅ bisa null
                    'note' => $request->note,
                    'updated_by' => auth()->id(),
                ]);

                SaleDeliveryItem::where('sale_delivery_id', (int) $saleDelivery->id)->delete();

                foreach ($request->items as $row) {

                    // ✅ FIX: 0 / negatif dianggap "tidak diisi" => NULL
                    $price = array_key_exists('price', $row) && $row['price'] !== null
                        ? (int) $row['price']
                        : null;

                    if ($price !== null && $price <= 0) {
                        $price = null;
                    }

                    SaleDeliveryItem::create([
                        'sale_delivery_id' => (int) $saleDelivery->id,

                        // ✅ kalau kamu memang mau item punya warehouse sendiri nanti:
                        // 'warehouse_id' => $warehouseId,
                        // tapi untuk sekarang tetap null biar konsisten sama flow kamu.
                        'warehouse_id' => null,

                        'product_id' => (int) $row['product_id'],
                        'quantity' => (int) $row['quantity'],
                        'price' => $price,
                    ]);
                }
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
