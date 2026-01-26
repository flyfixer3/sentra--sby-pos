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

    Route::get('/{saleOrder}', 'SaleOrderController@show')
        ->name('sale-orders.show');
});

