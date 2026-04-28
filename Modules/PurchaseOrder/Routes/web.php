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
        ->middleware('branch.selected')
        ->name('purchase-order-purchases.create');

    // Resource PO (index/show are read-only)
    Route::get('/purchase-orders', 'PurchaseOrderController@index')
        ->name('purchase-orders.index');

    Route::middleware('branch.selected')->group(function () {
        Route::get('/purchase-orders/create', 'PurchaseOrderController@create')
            ->name('purchase-orders.create');
        Route::post('/purchase-orders', 'PurchaseOrderController@store')
            ->name('purchase-orders.store');
        Route::get('/purchase-orders/{purchase_order}/edit', 'PurchaseOrderController@edit')
            ->name('purchase-orders.edit');
        Route::match(['put', 'patch'], '/purchase-orders/{purchase_order}', 'PurchaseOrderController@update')
            ->name('purchase-orders.update');
        Route::delete('/purchase-orders/{purchase_order}', 'PurchaseOrderController@destroy')
            ->name('purchase-orders.destroy');
    });

    Route::get('/purchase-orders/{purchase_order}', 'PurchaseOrderController@show')
        ->name('purchase-orders.show');
});
