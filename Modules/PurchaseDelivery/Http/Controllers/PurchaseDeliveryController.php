<?php

namespace Modules\PurchaseDelivery\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\PurchaseOrder\Entities\PurchaseOrder;
use Modules\PurchaseOrder\Entities\PurchaseOrderDetails;
use Modules\PurchaseDelivery\Entities\PurchaseDelivery;
use Modules\PurchaseDelivery\Entities\PurchaseDeliveryDetails;

class PurchaseDeliveryController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('access_purchase_deliveries'), 403);
        $purchaseDeliveries = PurchaseDelivery::with('purchaseOrder')->latest()->paginate(10);
        return view('purchasedelivery::index', compact('purchaseDeliveries'));
    }

    public function create(PurchaseOrder $purchaseOrder)
    {
        abort_if(Gate::denies('create_purchase_deliveries'), 403);

        // Fetch products that have remaining quantities
        $remainingItems = $purchaseOrder->purchaseOrderDetails()
            ->whereColumn('quantity', '>', 'fulfilled_quantity')
            ->get();

        if ($remainingItems->isEmpty()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder->id)
                ->with('error', 'All items have been delivered.');
        }

        return view('purchasedelivery::create', compact('purchaseOrder', 'remainingItems'));
    }

    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            $purchaseOrder = PurchaseOrder::findOrFail($request->purchase_order_id);

            // Ensure at least one item is selected
            if (!isset($request->quantity) || count(array_filter($request->quantity)) == 0) {
                throw new \Exception("You must select at least one item to create a Purchase Delivery.");
            }

            $purchaseDelivery = PurchaseDelivery::create([
                'purchase_order_id' => $purchaseOrder->id,
                'date' => $request->date,
                'ship_via' => $request->ship_via,
                'tracking_number' => $request->tracking_number,
                'note' => $request->note,
            ]);

            foreach ($request->quantity as $detail_id => $quantity) {
                if ($quantity > 0) {
                    $purchaseOrderDetail = PurchaseOrderDetails::findOrFail($detail_id);
                    $product = $purchaseOrderDetail->product;

                    PurchaseDeliveryDetails::create([
                        'purchase_delivery_id' => $purchaseDelivery->id,
                        'product_id' => $purchaseOrderDetail->product_id,
                        'product_name' => $product->product_name,
                        'product_code' => $product->product_code,
                        'quantity' => $quantity,
                        'description' => $request->description[$detail_id] ?? '',
                    ]);

                    // Update fulfilled quantity in PO
                    $purchaseOrderDetail->update([
                        'fulfilled_quantity' => $purchaseOrderDetail->fulfilled_quantity + $quantity
                    ]);
                }
            }

            // Update PO status
            $remainingQty = $purchaseOrder->purchaseOrderDetails->sum(fn($detail) => $detail->quantity - $detail->fulfilled_quantity);
            $purchaseOrder->update(['status' => $remainingQty > 0 ? 'Partially Sent' : 'Completed']);
        });

        return redirect()->route('purchase-orders.show', $request->purchase_order_id)
            ->with('success', 'Purchase Delivery Created Successfully');
    }

    public function show(PurchaseDelivery $purchaseDelivery)
    {
        return view('purchasedelivery::show', compact('purchaseDelivery'));
    }

    public function edit($id)
    {
        return view('purchasedelivery::edit');
    }

    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $purchaseDelivery = PurchaseDelivery::findOrFail($id); // ✅ Find manually
            $purchaseOrder = $purchaseDelivery->purchaseOrder;
    
            // Revert fulfilled quantities
            foreach ($purchaseDelivery->purchaseDeliveryDetails as $detail) {
                $purchaseOrderDetail = PurchaseOrderDetails::where('purchase_order_id', $purchaseOrder->id)
                    ->where('product_id', $detail->product_id)
                    ->first();
    
                if ($purchaseOrderDetail) {
                    $purchaseOrderDetail->update([
                        'fulfilled_quantity' => max(0, $purchaseOrderDetail->fulfilled_quantity - $detail->quantity)
                    ]);
                }
            }
    
            // Delete records
            $purchaseDelivery->purchaseDeliveryDetails()->delete();
            $purchaseDelivery->delete();
    
            // Update Purchase Order status
            $remainingQty = $purchaseOrder->purchaseOrderDetails->sum(fn($detail) => $detail->quantity - $detail->fulfilled_quantity);
            $purchaseOrder->update([
                'status' => $remainingQty == 0 ? 'Completed' : ($remainingQty < $purchaseOrder->purchaseOrderDetails->sum('quantity') ? 'Partially Sent' : 'Pending')
            ]);
        });
    
        return response()->json(['success' => true, 'message' => 'Purchase Delivery Deleted Successfully!']);
    }
    
    public function bulkDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array']);

        DB::transaction(function () use ($request) {
            $deliveries = PurchaseDelivery::whereIn('id', $request->ids)->get();

            foreach ($deliveries as $purchaseDelivery) {
                $purchaseOrder = $purchaseDelivery->purchaseOrder;

                // Revert fulfilled quantities in the PO
                foreach ($purchaseDelivery->purchaseDeliveryDetails as $detail) {
                    $purchaseOrderDetail = PurchaseOrderDetails::where('purchase_order_id', $purchaseOrder->id)
                        ->where('product_id', $detail->product_id)
                        ->first();

                    if ($purchaseOrderDetail) {
                        $purchaseOrderDetail->update([
                            'fulfilled_quantity' => max(0, $purchaseOrderDetail->fulfilled_quantity - $detail->quantity)
                        ]);
                    }
                }

                // Delete Purchase Delivery and its details
                $purchaseDelivery->purchaseDeliveryDetails()->delete();
                $purchaseDelivery->delete();

                // Update PO status dynamically
                $remainingQty = $purchaseOrder->purchaseOrderDetails->sum(fn($detail) => $detail->quantity - $detail->fulfilled_quantity);
                $purchaseOrder->update([
                    'status' => $remainingQty == 0 ? 'Completed' : ($remainingQty < $purchaseOrder->purchaseOrderDetails->sum('quantity') ? 'Partially Sent' : 'Pending')
                ]);
            }
        });

        return response()->json(['message' => 'Selected Purchase Deliveries deleted successfully!']);
    }
}
