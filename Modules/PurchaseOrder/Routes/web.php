<?php

use Illuminate\Support\Facades\Route;
use Modules\PurchaseOrder\Entities\PurchaseOrder;

Route::middleware(['auth'])->group(function () {

    // PDF Purchase Order
    Route::get('/purchase-orders/pdf/{purchase_order}', function ($purchase_order) {
        $purchase_order = PurchaseOrder::findOrFail($purchase_order);
        $supplier = \Modules\People\Entities\Supplier::findOrFail($purchase_order->supplier_id);

        $pdf = \PDF::loadView('purchase-orders::print', [
            'purchase_order' => $purchase_order,
            'supplier' => $supplier,
        ])->setPaper('a4');

        return $pdf->stream('purchase-order-' . $purchase_order->reference . '.pdf');
    })->name('purchase-orders.pdf');

    // Send email PO
    Route::get('/purchase-order/mail/{purchaseorder}', 'SendPurchaseOrderEmailController')
        ->name('purchase-order.email');

    // Convert PO -> Purchase (existing)
    Route::get('/purchase-order-purchases/{purchaseorder}', 'PurchaseOrderPurchasesController')
        ->name('purchase-order-purchases.create');

    // Resource PO
    Route::resource('purchase-orders', 'PurchaseOrderController');
});
