<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware' => 'auth'], function () {
    //Generate PDF
    Route::get('/purchase-orders/pdf/{id}', function ($id) {
        $purchase_order = \Modules\PurchaseOrder\Entities\PurchaseOrder::findOrFail($id);
        $supplier = \Modules\People\Entities\Supplier::findOrFail($purchase_order->supplier_id);

        $pdf = \PDF::loadView('purchase-orders::print', [
            'purchase_order' => $purchase_order,
            'supplier' => $supplier,
        ])->setPaper('a4');

        return $pdf->stream('purchase-order-'. $purchase_order->reference .'.pdf');
    })->name('purchase-orders.pdf');

    //Send PurchaseOrder Mail
    Route::get('/purchase-order/mail/{purchaseorder}', 'SendPurchaseOrderEmailController')->name('purchase-order.email');
    
    //Sales Form PurchaseOrder
    Route::get('/purchase-order-purchases/{purchaseorder}', 'PurchaseOrderPurchasesController')->name('purchase-order-purchases.create');
    //purchase orders
    Route::resource('purchase-orders', 'PurchaseOrderController');

});

