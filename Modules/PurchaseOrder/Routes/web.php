<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    // PDF Purchase Order
    Route::get('/purchase-orders/pdf/{purchase_order}', 'PurchaseOrderController@pdf')
        ->middleware('branch.selected')
        ->name('purchase-orders.pdf');

    // Send email PO
    Route::get('/purchase-order/mail/{purchaseorder}', 'SendPurchaseOrderEmailController')
        ->name('purchase-order.email');

    // Convert PO -> Purchase (existing)
    Route::get('/purchase-order-purchases/{purchaseorder}', 'PurchaseOrderPurchasesController')
        ->name('purchase-order-purchases.create');

    // Resource PO
    Route::resource('purchase-orders', 'PurchaseOrderController');
});
