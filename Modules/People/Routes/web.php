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

    //Customers
    Route::post('customers/{customer}/vehicles', 'CustomersController@storeVehicle')->name('customers.vehicles.store');
    Route::patch('customers/{customer}/vehicles/{vehicle}', 'CustomersController@updateVehicle')->name('customers.vehicles.update');
    Route::delete('customers/{customer}/vehicles/{vehicle}', 'CustomersController@destroyVehicle')->name('customers.vehicles.destroy');
    Route::resource('customers', 'CustomersController');
    //Suppliers
    Route::resource('suppliers', 'SuppliersController');

});
