<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth'], function () {

    /**
     * ============================
     * READ-ONLY ROUTES (ALLOW ALL BRANCH)
     * ============================
     */

    // Sales list & detail (read-only -> boleh ALL)
    Route::resource('sales', 'SaleController')->only(['index', 'show']);

    // Generate PDF (read-only -> boleh ALL)
    Route::get('/sales/pdf/{id}', function ($id) {
        $sale = \Modules\Sale\Entities\Sale::findOrFail($id);
        $customer = \Modules\People\Entities\Customer::findOrFail($sale->customer_id);

        $pdf = \PDF::loadView('sale::print', [
            'sale' => $sale,
            'customer' => $customer,
        ])->setPaper('a4');

        return $pdf->stream('sale-' . $sale->reference . '.pdf');
    })->name('sales.pdf');

    Route::get('/sales/pos/pdf/{id}', function ($id) {
        $sale = \Modules\Sale\Entities\Sale::findOrFail($id);
        $customer = \Modules\People\Entities\Customer::findOrFail($sale->customer_id);

        $pdf = Pdf::loadView('sale::print-pos', [
            'sale' => $sale,
            'customer' => $customer,
        ])->setPaper('a5', 'landscape');

        return $pdf->stream('sale-' . $sale->reference . '.pdf');
    })->name('sales.pos.pdf');

    Route::get('/sales/pos/debug/{id}', function ($id) {
        $sale = \Modules\Sale\Entities\Sale::findOrFail($id);
        $customer = \Modules\People\Entities\Customer::findOrFail($sale->customer_id);

        return view('sale::print-pos', [
            'sale' => $sale,
            'customer' => $customer,
        ]);
    })->name('sales.pos.debug');


    /**
     * ============================
     * WRITE ROUTES (MUST SELECT BRANCH)
     * ============================
     */
    Route::group(['middleware' => 'branch.selected'], function () {

        // POS (transaksi -> wajib branch)
        Route::get('/app/pos', 'PosController@index')->name('app.pos.index');
        Route::post('/app/pos', 'PosController@store')->name('app.pos.store');

        // Sales create/update/delete (write -> wajib branch)
        Route::resource('sales', 'SaleController')->except(['index', 'show']);

        // Payments (write -> wajib branch)
        Route::get('/sale-payments/{sale_id}', 'SalePaymentsController@index')->name('sale-payments.index');
        Route::get('/sale-payments/{sale_id}/create', 'SalePaymentsController@create')->name('sale-payments.create');
        Route::post('/sale-payments/store', 'SalePaymentsController@store')->name('sale-payments.store');
        Route::get('/sale-payments/{sale_id}/edit/{salePayment}', 'SalePaymentsController@edit')->name('sale-payments.edit');
        Route::patch('/sale-payments/update/{salePayment}', 'SalePaymentsController@update')->name('sale-payments.update');
        Route::delete('/sale-payments/destroy/{salePayment}', 'SalePaymentsController@destroy')->name('sale-payments.destroy');
    });
});
