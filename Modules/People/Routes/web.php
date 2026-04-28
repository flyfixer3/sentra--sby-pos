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

    //Customers (write actions guarded; define before show routes)
    Route::middleware('branch.selected')->group(function () {
        Route::get('customers/create', 'CustomersController@create')
            ->name('customers.create');
        Route::post('customers', 'CustomersController@store')
            ->name('customers.store');
        Route::get('customers/{customer}/edit', 'CustomersController@edit')
            ->name('customers.edit');
        Route::match(['put', 'patch'], 'customers/{customer}', 'CustomersController@update')
            ->name('customers.update');
        Route::delete('customers/{customer}', 'CustomersController@destroy')
            ->name('customers.destroy');

        Route::post('customers/{customer}/vehicles', 'CustomersController@storeVehicle')
            ->name('customers.vehicles.store');
        Route::patch('customers/{customer}/vehicles/{vehicle}', 'CustomersController@updateVehicle')
            ->name('customers.vehicles.update');
        Route::delete('customers/{customer}/vehicles/{vehicle}', 'CustomersController@destroyVehicle')
            ->name('customers.vehicles.destroy');
    });

    Route::get('customers', 'CustomersController@index')
        ->name('customers.index');
    Route::get('customers/{customer}', 'CustomersController@show')
        ->name('customers.show');
    //Suppliers
    Route::resource('suppliers', 'SuppliersController');

});
