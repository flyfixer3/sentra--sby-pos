<?php

use Illuminate\Support\Facades\Route;

use Modules\SaleDelivery\Http\Controllers\SaleDeliveryController;
use Modules\SaleDelivery\Http\Controllers\SaleDeliveryConfirmController;
use Modules\SaleDelivery\Http\Controllers\SaleDeliveryPrintController;

Route::group(['middleware' => 'auth'], function () {

    // WRITE (branch.selected)
    Route::group(['middleware' => 'branch.selected'], function () {

        // CREATE / STORE
        Route::get('sale-deliveries/create', [SaleDeliveryController::class, 'create'])->name('sale-deliveries.create');
        Route::post('sale-deliveries', [SaleDeliveryController::class, 'store'])->name('sale-deliveries.store');

        // CONFIRM
        Route::get('sale-deliveries/{saleDelivery}/confirm', [SaleDeliveryConfirmController::class, 'confirmForm'])
            ->name('sale-deliveries.confirm.form');
        Route::post('sale-deliveries/{saleDelivery}/confirm', [SaleDeliveryConfirmController::class, 'confirmStore'])
            ->name('sale-deliveries.confirm.store');

        // EDIT / UPDATE
        Route::get('sale-deliveries/{saleDelivery}/edit', [SaleDeliveryController::class, 'edit'])->name('sale-deliveries.edit');
        Route::put('sale-deliveries/{saleDelivery}', [SaleDeliveryController::class, 'update'])->name('sale-deliveries.update');

        // PRINT
        Route::post('sale-deliveries/{saleDelivery}/prepare-print', [SaleDeliveryPrintController::class, 'preparePrint'])
            ->name('sale-deliveries.prepare.print');
        Route::get('sale-deliveries/{saleDelivery}/print-pdf', [SaleDeliveryPrintController::class, 'printPdf'])
            ->name('sale-deliveries.print.pdf');

        // INVOICE
        Route::post('sale-deliveries/{saleDelivery}/create-invoice', [SaleDeliveryController::class, 'createInvoice'])
            ->name('sale-deliveries.create-invoice');

        // DELETE
        Route::delete('sale-deliveries/{saleDelivery}', [SaleDeliveryController::class, 'destroy'])
            ->name('sale-deliveries.destroy');
    });

    // READ (taruh paling bawah supaya tidak shadow "create/edit/confirm/print")
    Route::get('sale-deliveries', [SaleDeliveryController::class, 'index'])->name('sale-deliveries.index');
    Route::get('sale-deliveries/{saleDelivery}', [SaleDeliveryController::class, 'show'])->name('sale-deliveries.show');
});
