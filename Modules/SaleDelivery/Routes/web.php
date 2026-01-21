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

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth'], function () {

    // WRITE (branch.selected) - taruh atas agar gak ketabrak {saleDelivery}
    Route::group(['middleware' => 'branch.selected'], function () {
        Route::get('sale-deliveries/create', 'SaleDeliveryController@create')->name('sale-deliveries.create');
        Route::post('sale-deliveries', 'SaleDeliveryController@store')->name('sale-deliveries.store');

        Route::get('sale-deliveries/{saleDelivery}/confirm', 'SaleDeliveryController@confirmForm')->name('sale-deliveries.confirm.form');
        Route::post('sale-deliveries/{saleDelivery}/confirm', 'SaleDeliveryController@confirmStore')->name('sale-deliveries.confirm.store');
    });

    // READ
    Route::get('sale-deliveries', 'SaleDeliveryController@index')->name('sale-deliveries.index');
    Route::get('sale-deliveries/{saleDelivery}', 'SaleDeliveryController@show')->name('sale-deliveries.show');
});

