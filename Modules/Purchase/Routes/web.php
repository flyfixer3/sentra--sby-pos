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

use Modules\People\Entities\Supplier;
use Modules\Purchase\Entities\Purchase;
use Modules\Purchase\Http\Controllers\PurchaseController;

Route::group(['middleware' => 'auth'], function () {

    // ============================
    // WRITE routes (must select branch) - HARUS DI ATAS
    // ============================
    Route::group(['middleware' => 'branch.selected'], function () {

        // create/store (harus sebelum purchases/{purchase})
        Route::get('purchases/create', 'PurchaseController@create')->name('purchases.create');
        Route::post('purchases', 'PurchaseController@store')->name('purchases.store');

        // extra actions yang punya segment spesifik - aman kalau taruh sebelum {purchase}
        Route::get('purchases/create-from-delivery/{purchase_delivery}', 'PurchaseController@createFromDelivery')
            ->name('purchases.createFromDelivery');

        // edit/update/destroy (lebih spesifik dari {purchase})
        Route::get('purchases/{purchase}/edit', 'PurchaseController@edit')->name('purchases.edit');
        Route::put('purchases/{purchase}', 'PurchaseController@update')->name('purchases.update');
        Route::delete('purchases/{purchase}', 'PurchaseController@destroy')->name('purchases.destroy');

        // restore/force-destroy
        Route::patch('purchases/{purchase}/restore', [PurchaseController::class, 'restore'])
            ->name('purchases.restore');
        Route::delete('purchases/{purchase}/force-destroy', [PurchaseController::class, 'forceDestroy'])
            ->name('purchases.force-destroy');

        // Payments (write)
        Route::get('purchase-payments/{purchase_id}', 'PurchasePaymentsController@index')->name('purchase-payments.index');
        Route::get('purchase-payments/{purchase_id}/create', 'PurchasePaymentsController@create')->name('purchase-payments.create');
        Route::post('purchase-payments/store', 'PurchasePaymentsController@store')->name('purchase-payments.store');
        Route::get('purchase-payments/{purchase_id}/edit/{purchasePayment}', 'PurchasePaymentsController@edit')->name('purchase-payments.edit');
        Route::patch('purchase-payments/update/{purchasePayment}', 'PurchasePaymentsController@update')->name('purchase-payments.update');
        Route::delete('purchase-payments/destroy/{purchasePayment}', 'PurchasePaymentsController@destroy')->name('purchase-payments.destroy');
    });

    // ============================
    // READ routes
    // ============================
    Route::get('purchases', 'PurchaseController@index')->name('purchases.index');
    Route::get('purchases/{purchase}', 'PurchaseController@show')->name('purchases.show');

    // PDF (read-only)
    Route::get('purchases/pdf/{id}', function ($id) {
        $purchase  = Purchase::findOrFail($id);
        $supplier  = Supplier::findOrFail($purchase->supplier_id);

        $pdf = \PDF::loadView('purchase::print', [
            'purchase'  => $purchase,
            'supplier'  => $supplier,
        ])->setPaper('a4');

        return $pdf->stream('purchase-' . $purchase->reference . '.pdf');
    })->name('purchases.pdf');
});
