<?php

namespace Modules\SaleOrder\Http\Controllers;

use App\Support\BranchContext;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Warehouse;
use Modules\Quotation\Entities\Quotation;
use Modules\Sale\Entities\Sale;
use Modules\SaleOrder\DataTables\SaleOrdersDataTable;
use Modules\SaleOrder\Entities\SaleOrder;
use Modules\SaleOrder\Entities\SaleOrderItem;

class SaleOrderController extends Controller
{
    public function index(SaleOrdersDataTable $dataTable)
    {
        abort_if(Gate::denies('access_sale_orders'), 403);
        return $dataTable->render('saleorder::index');
    }

    public function create(Request $request)
    {
        abort_if(Gate::denies('create_sale_orders'), 403);

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

        $prefillCustomerId = null;
        $prefillWarehouseId = null;
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
            $prefillDate = (string) $quotation->date;
            $prefillNote = 'Created from Quotation #' . ($quotation->reference ?? $quotation->id);
            $prefillRefText = 'Quotation: ' . ($quotation->reference ?? $quotation->id);

            foreach ($quotation->quotationDetails as $d) {
                $prefillItems[] = [
                    'product_id' => (int) $d->product_id,
                    'quantity' => (int) $d->quantity,
                    'price' => (int) $d->price,
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
            $prefillDate = (string) $sale->date;
            $prefillNote = 'Created from Invoice #' . ($sale->reference ?? $sale->id);
            $prefillRefText = 'Invoice: ' . ($sale->reference ?? $sale->id);

            foreach ($sale->saleDetails as $d) {
                $prefillItems[] = [
                    'product_id' => (int) $d->product_id,
                    'quantity' => (int) $d->quantity,
                    'price' => (int) $d->price,
                ];
            }

            // kalau semua item invoice di warehouse yang sama, boleh auto set
            $whIds = $sale->saleDetails->pluck('warehouse_id')->filter()->unique()->values();
            if ($whIds->count() === 1) {
                $prefillWarehouseId = (int) $whIds->first();
            }
        }

        return view('saleorder::create', compact(
            'customers',
            'warehouses',
            'source',
            'prefillCustomerId',
            'prefillWarehouseId',
            'prefillDate',
            'prefillNote',
            'prefillItems',
            'prefillRefText'
        ));
    }

    public function store(Request $request)
    {
        abort_if(Gate::denies('create_sale_orders'), 403);

        $branchId = BranchContext::id();

        $source = (string) $request->get('source', 'manual');
        abort_unless(in_array($source, ['manual', 'quotation', 'sale'], true), 403);

        $rules = [
            'date' => 'required|date',
            'customer_id' => 'required|integer',
            'warehouse_id' => 'nullable|integer',
            'note' => 'nullable|string|max:5000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'nullable|integer|min:0',
        ];

        if ($source === 'quotation') $rules['quotation_id'] = 'required|integer';
        if ($source === 'sale') $rules['sale_id'] = 'required|integer';

        $request->validate($rules);

        $saleOrderId = null;

        DB::transaction(function () use ($request, $branchId, $source, &$saleOrderId) {

            // validasi customer global/branch
            $customer = Customer::query()
                ->where('id', (int) $request->customer_id)
                ->where(function ($q) use ($branchId) {
                    $q->whereNull('branch_id')
                      ->orWhere('branch_id', $branchId);
                })
                ->firstOrFail();

            $quotationId = null;
            $saleId = null;

            if ($source === 'quotation') {
                $quotationId = (int) $request->quotation_id;

                $q = Quotation::query()
                    ->where('id', $quotationId)
                    ->where('branch_id', $branchId)
                    ->firstOrFail();

                // optional: cegah double SO untuk quotation yang sama
                $exists = SaleOrder::query()
                    ->where('branch_id', $branchId)
                    ->where('quotation_id', $quotationId)
                    ->exists();

                if ($exists) {
                    abort(422, 'Sale Order for this quotation already exists.');
                }
            }

            if ($source === 'sale') {
                $saleId = (int) $request->sale_id;

                $s = Sale::query()
                    ->where('id', $saleId)
                    ->where('branch_id', $branchId)
                    ->firstOrFail();

                // optional: cegah double SO untuk invoice yang sama
                $exists = SaleOrder::query()
                    ->where('branch_id', $branchId)
                    ->where('sale_id', $saleId)
                    ->exists();

                if ($exists) {
                    abort(422, 'Sale Order for this invoice already exists.');
                }
            }

            if ($request->warehouse_id) {
                Warehouse::query()
                    ->where('branch_id', $branchId)
                    ->where('id', (int) $request->warehouse_id)
                    ->firstOrFail();
            }

            $so = SaleOrder::create([
                'branch_id' => $branchId,
                'customer_id' => (int) $customer->id,
                'quotation_id' => $quotationId,
                'sale_id' => $saleId,
                'warehouse_id' => $request->warehouse_id ? (int) $request->warehouse_id : null,
                'date' => $request->date,
                'status' => 'pending',
                'note' => $request->note,
            ]);

            $saleOrderId = (int) $so->id;

            foreach ($request->items as $row) {
                SaleOrderItem::create([
                    'sale_order_id' => $so->id,
                    'product_id' => (int) $row['product_id'],
                    'quantity' => (int) $row['quantity'],
                    'price' => array_key_exists('price', $row) && $row['price'] !== null ? (int) $row['price'] : null,
                ]);
            }
        });

        toast('Sale Order Created!', 'success');
        return redirect()->route('sale-orders.show', $saleOrderId);
    }

    public function show(SaleOrder $saleOrder)
    {
        abort_if(Gate::denies('show_sale_orders'), 403);

        $branchId = BranchContext::id();
        if ((int) $saleOrder->branch_id !== (int) $branchId) {
            abort(403, 'Wrong branch context.');
        }

        $saleOrder->load([
            'customer',
            'warehouse',
            'items.product',
            'deliveries' => function ($q) {
                $q->orderBy('id', 'desc');
            },
        ]);

        $remainingMap = $this->getRemainingQtyBySaleOrder((int) $saleOrder->id);

        return view('saleorder::show', compact('saleOrder', 'remainingMap'));
    }

    private function getRemainingQtyBySaleOrder(int $saleOrderId): array
    {
        $ordered = DB::table('sale_order_items')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->where('sale_order_id', $saleOrderId)
            ->groupBy('product_id')
            ->get();

        $shipped = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_order_id', $saleOrderId)
            ->where(function ($q) {
                $q->whereNotNull('sd.confirmed_at')
                  ->orWhereIn(DB::raw('LOWER(sd.status)'), ['confirmed', 'partial']);
            })
            ->select(
                'sdi.product_id',
                DB::raw('SUM(
                    CASE
                        WHEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0)) > 0
                            THEN (COALESCE(sdi.qty_good,0) + COALESCE(sdi.qty_defect,0) + COALESCE(sdi.qty_damaged,0))
                        ELSE COALESCE(sdi.quantity,0)
                    END
                ) as qty')
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
}
