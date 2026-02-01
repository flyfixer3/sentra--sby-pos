<?php

namespace Modules\Quotation\Http\Controllers;

use App\Support\BranchContext;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;
use Modules\SaleDelivery\Entities\SaleDelivery;
use Modules\SaleDelivery\Entities\SaleDeliveryItem;
use Modules\Quotation\DataTables\QuotationsDataTable;
use Modules\Quotation\Entities\Quotation;
use Modules\Quotation\Entities\QuotationDetails;
use Modules\Quotation\Http\Requests\StoreQuotationRequest;
use Modules\Quotation\Http\Requests\UpdateQuotationRequest;
use Modules\Sale\Entities\Sale;
use Modules\Sale\Entities\SaleDetails;

class QuotationController extends Controller
{

    public function index(QuotationsDataTable $dataTable) {
        abort_if(Gate::denies('access_quotations'), 403);

        return $dataTable->render('quotation::index');
    }

    public function create()
    {
        abort_if(Gate::denies('create_quotations'), 403);

        Cart::instance('quotation')->destroy();

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

        return view('quotation::create', compact('customers', 'warehouses'));
    }

    public function store(StoreQuotationRequest $request)
    {
        $branchId = BranchContext::id(); // wajib ada

        DB::transaction(function () use ($request, $branchId) {

            // ✅ validasi customer boleh untuk branch aktif / global
            $customer = Customer::query()
                ->where('id', $request->customer_id)
                ->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
                })
                ->firstOrFail();

            $quotation = Quotation::create([
                'branch_id'           => $branchId,
                'date'                => $request->date,
                'customer_id'         => $customer->id,
                'customer_name'       => $customer->customer_name,
                'tax_percentage'      => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount'     => (int) $request->shipping_amount,
                'total_amount'        => (int) $request->total_amount,
                'status'              => $request->status,
                'note'                => $request->note,
                'tax_amount'          => (int) Cart::instance('quotation')->tax(),
                'discount_amount'     => (int) Cart::instance('quotation')->discount(),
            ]);

            foreach (Cart::instance('quotation')->content() as $cart_item) {
                QuotationDetails::create([
                    'quotation_id' => $quotation->id,
                    'product_id' => $cart_item->id,
                    'product_name' => $cart_item->name,
                    'product_code' => $cart_item->options->code,
                    'quantity' => $cart_item->qty,
                    'price' => (int) $cart_item->price,
                    'unit_price' => (int) $cart_item->options->unit_price,
                    'sub_total' => (int) $cart_item->options->sub_total,
                    'product_discount_amount' => (int) $cart_item->options->product_discount,
                    'product_discount_type' => $cart_item->options->product_discount_type,
                    'product_tax_amount' => (int) $cart_item->options->product_tax,
                ]);
            }

            Cart::instance('quotation')->destroy();
        });

        toast('Quotation Created!', 'success');
        return redirect()->route('quotations.index');
    }

    public function show(Quotation $quotation) {
        abort_if(Gate::denies('show_quotations'), 403);

        $branchId = BranchContext::id();

        $customer = Customer::query()
        ->where('id', $quotation->customer_id)
        ->where(fn($q)=>$q->whereNull('branch_id')->orWhere('branch_id',$branchId))
        ->firstOrFail();

        return view('quotation::show', compact('quotation', 'customer'));
    }


    public function edit(Quotation $quotation) {
        abort_if(Gate::denies('edit_quotations'), 403);

        $quotation_details = $quotation->quotationDetails;

        $branchId = BranchContext::id();

        Cart::instance('quotation')->destroy();

        $cart = Cart::instance('quotation');

        foreach ($quotation_details as $quotation_detail) {
            $cart->add([
                'id'      => $quotation_detail->product_id,
                'name'    => $quotation_detail->product_name,
                'qty'     => $quotation_detail->quantity,
                'price'   => $quotation_detail->price,
                'weight'  => 1,
                'options' => [
                    'product_discount' => $quotation_detail->product_discount_amount,
                    'product_discount_type' => $quotation_detail->product_discount_type,
                    'sub_total'   => $quotation_detail->sub_total,
                    'code'        => $quotation_detail->product_code,
                    'stock'       => Product::findOrFail($quotation_detail->product_id)->product_quantity,
                    'product_tax' => $quotation_detail->product_tax_amount,
                    'unit_price'  => $quotation_detail->unit_price
                ]
            ]);
        }

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

        return view('quotation::edit', compact('quotation', 'customers', 'warehouses'));
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation)
    {
        abort_if(Gate::denies('edit_quotations'), 403);

        $branchId = BranchContext::id(); // wajib ada (karena route write sudah pakai branch.selected)

        DB::transaction(function () use ($request, $quotation, $branchId) {

            // ✅ validasi customer harus global atau branch aktif
            $customer = Customer::query()
                ->where('id', $request->customer_id)
                ->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId);
                })
                ->firstOrFail();

            // hapus details lama
            foreach ($quotation->quotationDetails as $quotation_detail) {
                $quotation_detail->delete();
            }

            // ✅ update header quotation (branch-aware + legacy safe)
            $updateData = [
                'date'                => $request->date,
                'reference'           => $request->reference,
                'customer_id'         => $customer->id,
                'customer_name'       => $customer->customer_name,
                'tax_percentage'      => $request->tax_percentage,
                'discount_percentage' => $request->discount_percentage,
                'shipping_amount'     => (int) $request->shipping_amount,
                'total_amount'        => (int) $request->total_amount,
                'status'              => $request->status,
                'note'                => $request->note,
                'tax_amount'          => (int) Cart::instance('quotation')->tax(),
                'discount_amount'     => (int) Cart::instance('quotation')->discount(),
            ];

            // ✅ Opsional: kalau data lama branch_id masih NULL, isi dengan branch aktif
            if (\Illuminate\Support\Facades\Schema::hasColumn('quotations', 'branch_id')) {
                $updateData['branch_id'] = $quotation->branch_id ?? $branchId;
            }

            $quotation->update($updateData);

            // insert details baru dari cart
            foreach (Cart::instance('quotation')->content() as $cart_item) {
                QuotationDetails::create([
                    'quotation_id'             => $quotation->id,
                    'product_id'               => $cart_item->id,
                    'product_name'             => $cart_item->name,
                    'product_code'             => $cart_item->options->code,
                    'quantity'                 => (int) $cart_item->qty,
                    'price'                    => (int) $cart_item->price,
                    'unit_price'               => (int) $cart_item->options->unit_price,
                    'sub_total'                => (int) $cart_item->options->sub_total,
                    'product_discount_amount'  => (int) $cart_item->options->product_discount,
                    'product_discount_type'    => $cart_item->options->product_discount_type,
                    'product_tax_amount'       => (int) $cart_item->options->product_tax,
                ]);
            }

            Cart::instance('quotation')->destroy();
        });

        toast('Quotation Updated!', 'info');
        return redirect()->route('quotations.index');
    }

    public function destroy(Quotation $quotation) {
        abort_if(Gate::denies('delete_quotations'), 403);

        $quotation->delete();

        toast('Quotation Deleted!', 'warning');

        return redirect()->route('quotations.index');
    }

    public function createInvoiceDirect(Quotation $quotation)
    {
        abort_if(Gate::denies('create_sale_invoices'), 403);

        $branchId = BranchContext::id();
        if ((int) $quotation->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        try {
            $saleId = null;
            $saleDeliveryId = null;

            DB::transaction(function () use ($quotation, $branchId, &$saleId, &$saleDeliveryId) {

                $quotation = Quotation::query()
                    ->lockForUpdate()
                    ->with(['quotationDetails'])
                    ->findOrFail((int) $quotation->id);

                // ✅ Idempotent: kalau sudah pernah bikin invoice dari quotation ini, block
                // (sesuaikan kalau kamu punya kolom quotation_id di sales)
                $existsSale = Sale::query()
                    ->where('branch_id', $branchId)
                    ->where('quotation_id', (int) $quotation->id)
                    ->exists();

                if ($existsSale) {
                    abort(422, 'Sale Invoice for this quotation already exists.');
                }

                // ✅ Warehouse default untuk SD (pending)
                $warehouse = Warehouse::query()
                    ->where('branch_id', $branchId)
                    ->orderBy('warehouse_name')
                    ->first();

                if (!$warehouse) {
                    abort(422, 'No warehouse found in this branch. Please create a warehouse first.');
                }

                $dateRaw = (string) $quotation->getRawOriginal('date');
                if (trim($dateRaw) === '') $dateRaw = date('Y-m-d');

                // 1) ✅ Create SALE (Invoice) dulu
                $sale = Sale::create([
                    'branch_id'      => $branchId,
                    'quotation_id'   => (int) $quotation->id,     // pastikan ada kolom ini di sales
                    'customer_id'    => (int) $quotation->customer_id,
                    'customer_name'  => (string) $quotation->customer_name,
                    'date'           => $dateRaw,
                    'status'         => 'pending',               // atau 'unpaid' sesuai projectmu
                    'payment_status' => 'unpaid',                // sesuaikan
                    'tax_percentage' => (float) $quotation->tax_percentage,
                    'discount_percentage' => (float) $quotation->discount_percentage,
                    'shipping_amount' => (int) $quotation->shipping_amount,
                    'tax_amount'     => (int) $quotation->tax_amount,
                    'discount_amount'=> (int) $quotation->discount_amount,
                    'total_amount'   => (int) $quotation->total_amount,
                    'note'           => 'Direct Invoice from Quotation #' . ($quotation->reference ?? $quotation->id),
                    'created_by'     => auth()->id(),
                    'updated_by'     => auth()->id(),
                ]);

                $saleId = (int) $sale->id;

                // 2) ✅ Copy items ke sale_details
                foreach ($quotation->quotationDetails as $d) {
                    SaleDetails::create([
                        'sale_id'       => $saleId,
                        'product_id'    => (int) $d->product_id,
                        'product_name'  => (string) $d->product_name,
                        'product_code'  => (string) $d->product_code,
                        'quantity'      => (int) $d->quantity,
                        'price'         => (int) ($d->price ?? 0),
                        'unit_price'    => (int) ($d->unit_price ?? 0),
                        'sub_total'     => (int) ($d->sub_total ?? 0),
                        'tax_amount'    => (int) ($d->product_tax_amount ?? 0),
                        'discount_amount' => (int) ($d->product_discount_amount ?? 0),
                    ]);
                }

                // 3) ✅ Auto create SALE DELIVERY (pending) untuk mutation out nanti
                $sd = SaleDelivery::create([
                    'branch_id'    => $branchId,
                    'quotation_id' => (int) $quotation->id,
                    'sale_order_id'=> null,
                    'sale_id'      => $saleId, // 🔥 Link ke invoice
                    'customer_id'  => (int) $quotation->customer_id,
                    'customer_name'=> (string) $quotation->customer_name,
                    'warehouse_id' => (int) $warehouse->id,
                    'date'         => $dateRaw,
                    'status'       => 'pending',
                    'note'         => 'Auto-created from Quotation Invoice #' . ($quotation->reference ?? $quotation->id),
                    'created_by'   => auth()->id(),
                    'updated_by'   => auth()->id(),
                ]);

                $saleDeliveryId = (int) $sd->id;

                foreach ($quotation->quotationDetails as $d) {
                    SaleDeliveryItem::create([
                        'sale_delivery_id' => $saleDeliveryId,
                        'product_id'       => (int) $d->product_id,
                        'quantity'         => (int) $d->quantity,
                        'price'            => $d->price !== null ? (int) $d->price : null,
                    ]);
                }

                // 4) ✅ Mark quotation completed (pakai lowercase biar konsisten)
                $quotation->update([
                    'status' => 'completed',
                ]);
            });

            toast('Sales Invoice created. Sale Delivery is pending for manual confirm.', 'success');

            // kamu pilih mau redirect ke invoice atau ke delivery.
            // aku sarankan ke invoice dulu:
            return redirect()->route('sales.show', $saleId);

            // atau kalau kamu mau cek delivery:
            // return redirect()->route('sale-deliveries.show', $saleDeliveryId);

        } catch (\Throwable $e) {
            toast($e->getMessage(), 'error');
            return back()->withInput();
        }
    }
}
