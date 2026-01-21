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

use Modules\People\Entities\Customer;
use Modules\Quotation\Entities\Quotation;

Route::group(['middleware' => 'auth'], function () {

    // WRITE (must select branch) - taruh duluan biar gak ketabrak {quotation}
    Route::group(['middleware' => 'branch.selected'], function () {
        Route::get('quotations/create', 'QuotationController@create')->name('quotations.create');
        Route::post('quotations', 'QuotationController@store')->name('quotations.store');

        Route::get('quotations/{quotation}/edit', 'QuotationController@edit')->name('quotations.edit');
        Route::put('quotations/{quotation}', 'QuotationController@update')->name('quotations.update');
        Route::delete('quotations/{quotation}', 'QuotationController@destroy')->name('quotations.destroy');

        Route::get('/quotation-sales/{quotation}', 'QuotationSalesController')->name('quotation-sales.create');
        Route::get('/quotation/mail/{quotation}', 'SendQuotationEmailController')->name('quotation.email');
    });

    // READ-ONLY
    Route::get('quotations', 'QuotationController@index')->name('quotations.index');
    Route::get('quotations/{quotation}', 'QuotationController@show')->name('quotations.show');

    Route::get('/quotations/pdf/{id}', function ($id) {
        $quotation = Quotation::findOrFail($id);
        $customer = Customer::findOrFail($quotation->customer_id);

        $pdf = \PDF::loadView('quotation::print', [
            'quotation' => $quotation,
            'customer' => $customer,
        ])->setPaper('a4');

        return $pdf->stream('quotation-'. $quotation->reference .'.pdf');
    })->name('quotations.pdf');
});
