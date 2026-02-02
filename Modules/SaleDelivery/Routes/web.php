<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth'], function () {

    // WRITE (branch.selected)
    Route::group(['middleware' => 'branch.selected'], function () {

        Route::get('sale-deliveries/create', 'SaleDeliveryController@create')->name('sale-deliveries.create');
        Route::post('sale-deliveries', 'SaleDeliveryController@store')->name('sale-deliveries.store');

        Route::get('sale-deliveries/{saleDelivery}/confirm', 'SaleDeliveryController@confirmForm')->name('sale-deliveries.confirm.form');
        Route::post('sale-deliveries/{saleDelivery}/confirm', 'SaleDeliveryController@confirmStore')->name('sale-deliveries.confirm.store');

        Route::get('sale-deliveries/{saleDelivery}/edit', 'SaleDeliveryController@edit')->name('sale-deliveries.edit');
        Route::put('sale-deliveries/{saleDelivery}', 'SaleDeliveryController@update')->name('sale-deliveries.update');

        // ✅ PRINT PDF (optional)
        Route::post('sale-deliveries/{saleDelivery}/prepare-print', 'SaleDeliveryController@preparePrint')
            ->name('sale-deliveries.prepare.print');
        Route::get('sale-deliveries/{saleDelivery}/print-pdf', 'SaleDeliveryController@printPdf')
            ->name('sale-deliveries.print.pdf');
        Route::post('sale-deliveries/{saleDelivery}/create-invoice', 'SaleDeliveryController@createInvoice')
        ->name('sale-deliveries.create-invoice');
        Route::delete('sale-deliveries/{saleDelivery}', [
            \Modules\SaleDelivery\Http\Controllers\SaleDeliveryController::class,
            'destroy'
        ])->name('sale-deliveries.destroy');
    });

    // READ
    Route::get('sale-deliveries', 'SaleDeliveryController@index')->name('sale-deliveries.index');
    Route::get('sale-deliveries/{saleDelivery}', 'SaleDeliveryController@show')->name('sale-deliveries.show');
});
