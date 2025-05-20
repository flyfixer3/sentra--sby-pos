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

use Barryvdh\DomPDF\Facade\Pdf;

Route::group(['middleware' => 'auth'], function () {

    //POS
    Route::get('/app/pos', 'PosController@index')->name('app.pos.index');
    Route::post('/app/pos', 'PosController@store')->name('app.pos.store');

    //Generate PDF
    Route::get('/sales/pdf/{id}', function ($id) {
        $sale = \Modules\Sale\Entities\Sale::findOrFail($id);
        $customer = \Modules\People\Entities\Customer::findOrFail($sale->customer_id);

        $pdf = \PDF::loadView('sale::print', [
            'sale' => $sale,
            'customer' => $customer,
        ])->setPaper('a4');

        return $pdf->stream('sale-'. $sale->reference .'.pdf');
    })->name('sales.pdf');
    Route::get('/sales/pos/pdf/{id}', function ($id) {
        $sale = \Modules\Sale\Entities\Sale::findOrFail($id);
        $customer = \Modules\People\Entities\Customer::findOrFail($sale->customer_id);
    
        $pdf = Pdf::loadView('sale::print-pos', [
            'sale' => $sale,
            'customer' => $customer,
            'watermarkText' => "ANJING KAMU MICEL"
        ])->setPaper('a5', 'landscape');
    
        return $pdf->stream('sale-' . $sale->reference . '.pdf');
    })->name('sales.pos.pdf');

    Route::get('/sales/pos/debug/{id}', function ($id) {
        // Fetch the sale and customer data
        $sale = \Modules\Sale\Entities\Sale::findOrFail($id);
        $customer = \Modules\People\Entities\Customer::findOrFail($sale->customer_id);
    
        // Return the Blade view directly for debugging
        return view('sale::print-pos', [
            'sale' => $sale,
            'customer' => $customer,
        ]);
    })->name('sales.pos.debug');

    // Route::get('/sales/pos/pdf/{id}', 'PosController@show')->name('app.pos.show');

    //Sales
    Route::resource('sales', 'SaleController');

    //Payments
    Route::get('/sale-payments/{sale_id}', 'SalePaymentsController@index')->name('sale-payments.index');
    Route::get('/sale-payments/{sale_id}/create', 'SalePaymentsController@create')->name('sale-payments.create');
    Route::post('/sale-payments/store', 'SalePaymentsController@store')->name('sale-payments.store');
    Route::get('/sale-payments/{sale_id}/edit/{salePayment}', 'SalePaymentsController@edit')->name('sale-payments.edit');
    Route::patch('/sale-payments/update/{salePayment}', 'SalePaymentsController@update')->name('sale-payments.update');
    Route::delete('/sale-payments/destroy/{salePayment}', 'SalePaymentsController@destroy')->name('sale-payments.destroy');
});
