<?php

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

    Route::get('/{saleOrder}', 'SaleOrderController@show')
        ->name('sale-orders.show');

    // ✅ DELETE
    Route::delete('/{saleOrder}', 'SaleOrderController@destroy')
        ->name('sale-orders.destroy');
});
