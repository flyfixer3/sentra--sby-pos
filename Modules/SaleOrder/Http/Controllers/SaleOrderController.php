<?php

namespace Modules\SaleOrder\Http\Controllers;

use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\People\Entities\Customer;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\Warehouse;
use Modules\Quotation\Entities\Quotation;
use Modules\Sale\Entities\Sale;
use Modules\SaleOrder\DataTables\SaleOrdersDataTable;
use Modules\SaleOrder\Entities\SaleOrder;
use Modules\SaleOrder\Entities\SaleOrderItem;

class SaleOrderController extends Controller
{
    private function failBack(string $message, int $status = 422)
    {
        toast($message, 'error');
        return redirect()->back()->withInput();
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
            abort_unless(in_array($source, ['manual', 'quotation', 'sale'], true), 403);

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
                    $prefillItems[] = [
                        'product_id' => (int) $d->product_id,
                        'quantity'   => (int) $d->quantity,
                        'price'      => (int) $d->price,

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
                    $prefillItems[] = [
                        'product_id' => (int) $d->product_id,
                        'quantity'   => (int) $d->quantity,
                        'price'      => (int) $d->price,

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
            $branchId = BranchContext::id();

            $source = (string) $request->get('source', 'manual');
            abort_unless(in_array($source, ['manual', 'quotation', 'sale'], true), 403);

            $rules = [
                'date' => 'required|date',
                'customer_id' => 'required|integer',

                // ✅ SO warehouse tetap nullable (redundant)
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

                    Quotation::query()
                        ->where('id', $quotationId)
                        ->where('branch_id', $branchId)
                        ->firstOrFail();

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

                    Sale::query()
                        ->where('id', $saleId)
                        ->where('branch_id', $branchId)
                        ->firstOrFail();

                    $exists = SaleOrder::query()
                        ->where('branch_id', $branchId)
                        ->where('sale_id', $saleId)
                        ->exists();

                    if ($exists) {
                        abort(422, 'Sale Order for this invoice already exists.');
                    }
                }

                // ==========================
                // ✅ AGGREGATE QTY PER PRODUCT
                // ==========================
                $qtyByProduct = [];
                foreach ($request->items as $row) {
                    $pid = (int) ($row['product_id'] ?? 0);
                    $qty = (int) ($row['quantity'] ?? 0);
                    if ($pid <= 0 || $qty <= 0) continue;

                    if (!isset($qtyByProduct[$pid])) $qtyByProduct[$pid] = 0;
                    $qtyByProduct[$pid] += $qty;
                }

                if (empty($qtyByProduct)) {
                    abort(422, 'Items are empty.');
                }

                // ✅ Pastikan product_id valid
                $productIds = array_keys($qtyByProduct);
                $validCount = Product::query()->whereIn('id', $productIds)->count();
                if ($validCount !== count($productIds)) {
                    abort(422, 'Invalid product selected.');
                }

                // ==========================
                // ✅ CREATE SALE ORDER (header)
                // ==========================
                $so = SaleOrder::create([
                    'branch_id' => $branchId,
                    'customer_id' => (int) $customer->id,
                    'quotation_id' => $quotationId,
                    'sale_id' => $saleId,
                    'warehouse_id' => null,
                    'date' => $request->date,
                    'status' => 'pending',
                    'note' => $request->note,
                ]);

                $saleOrderId = (int) $so->id;

                // ==========================
                // ✅ CREATE ITEMS
                // ==========================
                foreach ($request->items as $row) {
                    SaleOrderItem::create([
                        'sale_order_id' => $so->id,
                        'product_id' => (int) $row['product_id'],
                        'quantity' => (int) $row['quantity'],
                        'price' => array_key_exists('price', $row) && $row['price'] !== null ? (int) $row['price'] : null,
                    ]);
                }

                // ==========================
                // ✅ STEP 2: INCREASE qty_reserved (stocks)
                // - reserve at branch-level (warehouse_id NULL)
                // ==========================
                foreach ($qtyByProduct as $pid => $qty) {
                    // lock row if exists (avoid race)
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
                        // create minimal row for branch-level reservation
                        DB::table('stocks')->insert([
                            'branch_id'    => (int) $branchId,
                            'warehouse_id' => null,
                            'product_id'   => (int) $pid,

                            'qty_available' => 0,
                            'qty_reserved'  => (int) $qty,
                            'qty_incoming'  => 0,
                            'min_stock'     => 0,

                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // mark quotation completed if needed (keep your rule)
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

        $remainingMap = $this->getRemainingQtyBySaleOrder($saleOrder->id);
        $plannedRemainingMap = $this->getPlannedRemainingQtyBySaleOrder($saleOrder->id);

        return view('saleorder::show', compact(
            'saleOrder',
            'remainingMap',
            'plannedRemainingMap'
        ));
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

            $saleOrder->load(['items']);

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
                return [
                    'product_id' => (int) $it->product_id,
                    'quantity'   => (int) $it->quantity,
                    'price'      => $it->price !== null ? (int) $it->price : null,
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

    // =========================
    // ✅ NEW: UPDATE SALE ORDER
    // =========================
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

                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'nullable|integer|min:0',
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
                        $q->whereNull('branch_id')
                          ->orWhere('branch_id', $branchId);
                    })
                    ->firstOrFail();

                // ✅ warehouse di SO kita keep NULL (biar gak redundant)
                $saleOrder->update([
                    'date' => $request->date,
                    'customer_id' => (int) $customer->id,
                    'warehouse_id' => null,
                    'note' => $request->note,
                ]);

                // replace items (pending only)
                SaleOrderItem::query()
                    ->where('sale_order_id', (int) $saleOrder->id)
                    ->delete();

                foreach ($request->items as $row) {
                    SaleOrderItem::create([
                        'sale_order_id' => (int) $saleOrder->id,
                        'product_id' => (int) $row['product_id'],
                        'quantity' => (int) $row['quantity'],
                        'price' => array_key_exists('price', $row) && $row['price'] !== null ? (int) $row['price'] : null,
                    ]);
                }
            });

            toast('Sale Order updated!', 'success');
            return redirect()->route('sale-orders.show', $saleOrder->id);

        } catch (\Throwable $e) {
            return $this->failBack($e->getMessage(), 422);
        }
    }

    // =========================
    // ✅ NEW: DELETE SALE ORDER
    // =========================
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

            // ✅ safety: kalau sudah ada Sale Delivery turunan, jangan boleh delete
            $hasDelivery = DB::table('sale_deliveries')
                ->where('sale_order_id', (int) $saleOrder->id)
                ->exists();

            if ($hasDelivery) {
                throw new \RuntimeException('Cannot delete. This Sale Order already has Sale Deliveries.');
            }

            DB::transaction(function () use ($saleOrder) {
                SaleOrderItem::query()
                    ->where('sale_order_id', (int) $saleOrder->id)
                    ->delete();

                $saleOrder->delete();
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
                  ->orWhereIn(DB::raw('LOWER(sd.status)'), ['confirmed']);
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

    private function getPlannedRemainingQtyBySaleOrder(int $saleOrderId): array
    {
        $ordered = DB::table('sale_order_items')
            ->select('product_id', DB::raw('SUM(quantity) as qty'))
            ->where('sale_order_id', $saleOrderId)
            ->groupBy('product_id')
            ->get();

        $planned = DB::table('sale_delivery_items as sdi')
            ->join('sale_deliveries as sd', 'sd.id', '=', 'sdi.sale_delivery_id')
            ->where('sd.sale_order_id', $saleOrderId)
            ->whereIn(DB::raw('LOWER(sd.status)'), ['pending', 'confirmed'])
            ->select('sdi.product_id', DB::raw('SUM(COALESCE(sdi.quantity,0)) as qty'))
            ->groupBy('sdi.product_id')
            ->get()
            ->keyBy('product_id');

        $remaining = [];

        foreach ($ordered as $row) {
            $pid = (int) $row->product_id;
            $orderedQty = (int) $row->qty;
            $plannedQty = isset($planned[$pid]) ? (int) $planned[$pid]->qty : 0;

            $rem = $orderedQty - $plannedQty;
            if ($rem < 0) $rem = 0;

            $remaining[$pid] = $rem;
        }

        return $remaining;
    }
}
