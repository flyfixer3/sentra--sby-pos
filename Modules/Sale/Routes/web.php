<?php

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth'], function () {
    /**
     * ============================
     * SALES (WRITE) - MUST SELECT BRANCH
     * ============================
     */
    Route::group(['middleware' => 'branch.selected'], function () {

        // ✅ Pastikan CREATE ada sebelum sales/{sale} secara urutan file (ini penting)
        Route::get('sales/create', 'SaleController@create')->name('sales.create');
        Route::post('sales', 'SaleController@store')->name('sales.store');

        Route::get('sales/{sale}/edit', 'SaleController@edit')->name('sales.edit');
        Route::put('sales/{sale}', 'SaleController@update')->name('sales.update');
        Route::delete('sales/{sale}', 'SaleController@destroy')->name('sales.destroy');

        // POS (transaksi -> wajib branch)
        Route::get('/app/pos', 'PosController@index')->name('app.pos.index');
        Route::post('/app/pos', 'PosController@store')->name('app.pos.store');

        // Payments (write -> wajib branch)
        Route::get('/sale-payments/{sale_id}', 'SalePaymentsController@index')->name('sale-payments.index');
        Route::get('/sale-payments/{sale_id}/create', 'SalePaymentsController@create')->name('sale-payments.create');
        Route::post('/sale-payments/store', 'SalePaymentsController@store')->name('sale-payments.store');
        Route::get('/sale-payments/{sale_id}/edit/{salePayment}', 'SalePaymentsController@edit')->name('sale-payments.edit');
        Route::patch('/sale-payments/update/{salePayment}', 'SalePaymentsController@update')->name('sale-payments.update');
        Route::delete('/sale-payments/destroy/{salePayment}', 'SalePaymentsController@destroy')->name('sale-payments.destroy');
    });

      /**
     * ============================
     * SALES (READ)
     * ============================
     */
    Route::get('sales', 'SaleController@index')->name('sales.index');
    Route::get('sales/{sale}', 'SaleController@show')->name('sales.show');

    /**
     * ============================
     * PDF (READ-ONLY)
     * ============================
     */
    // NOTE:
    // Jangan pakai alias \PDF, karena di config/app.php alias "PDF" ter-mapping ke Snappy (wkhtmltopdf).
    // Untuk shared hosting / environment tanpa wkhtmltopdf, ini akan error dan PDF gagal load.
    // Gunakan DomPDF facade (Barryvdh\DomPDF\Facade\Pdf) melalui controller.
    Route::get('/sales/pdf/{sale}', 'SaleController@pdf')->name('sales.pdf');

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
     * SALE PAYMENT RECEIPT (READ-ONLY)
     * ============================
     */
    Route::get('/sale-payments/receipt/{salePayment}', 'SalePaymentsController@receipt')->name('sale-payments.receipt');
    Route::get('/sale-payments/receipt/{salePayment}/debug', 'SalePaymentsController@receiptDebug')->name('sale-payments.receipt.debug');
});
