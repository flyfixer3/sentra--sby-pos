<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'middleware' => ['auth'],
    'prefix' => 'sale-orders',
], function () {

    Route::get('/', 'SaleOrderController@index')
        ->name('sale-orders.index');

    Route::get('/create', 'SaleOrderController@create')
        ->name('sale-orders.create');

    Route::post('/', 'SaleOrderController@store')
        ->name('sale-orders.store');

    // ✅ EDIT
    Route::get('/{saleOrder}/edit', 'SaleOrderController@edit')
        ->name('sale-orders.edit');

    // ✅ UPDATE
    Route::put('/{saleOrder}', 'SaleOrderController@update')
        ->name('sale-orders.update');

    // ✅ SHOW
    Route::get('/{saleOrder}', 'SaleOrderController@show')
        ->name('sale-orders.show');

    // ✅ Sale Order document print (independent from DP receipt)
    Route::get('/{saleOrder}/print', 'SaleOrderController@print')
        ->name('sale-orders.print');

    Route::get('/{saleOrder}/download', 'SaleOrderController@download')
        ->name('sale-orders.download');

    // ✅ NEW: DP Receipt (PDF + Debug)
    Route::get('/{saleOrder}/dp-receipt', 'SaleOrderController@dpReceipt')
        ->name('sale-orders.dp-receipt');

    Route::get('/{saleOrder}/dp-receipt/debug', 'SaleOrderController@dpReceiptDebug')
        ->name('sale-orders.dp-receipt.debug');

    // ✅ DELETE
    Route::delete('/{saleOrder}', 'SaleOrderController@destroy')
        ->name('sale-orders.destroy');
});
