<?php

use Illuminate\Support\Facades\Route;
use Modules\Transfer\Http\Controllers\TransfersIndexController;
use Modules\Transfer\Http\Controllers\TransferController;
use Modules\Transfer\Http\Controllers\TransferQualityReportController;

Route::middleware(['auth'])
    ->prefix('transfers')
    ->name('transfers.')
    ->group(function () {

        Route::get('/', [TransfersIndexController::class, 'index'])->name('index');
        Route::get('/outgoing', [TransfersIndexController::class, 'outgoing'])->name('outgoing');
        Route::get('/incoming', [TransfersIndexController::class, 'incoming'])->name('incoming');

        Route::get('/datatable/outgoing', [TransfersIndexController::class, 'outgoingData'])->name('datatable.outgoing');
        Route::get('/datatable/incoming', [TransfersIndexController::class, 'incomingData'])->name('datatable.incoming');

        Route::get('/create', [TransferController::class, 'create'])->name('create');
        Route::post('/', [TransferController::class, 'store'])->name('store');

        Route::get('/{transfer}/show', [TransferController::class, 'show'])->name('show');

        Route::get('/{transfer}/confirm', [TransferController::class, 'showConfirmationForm'])->name('confirm');
        Route::put('/{transfer}/confirm', [TransferController::class, 'storeConfirmation'])->name('confirm.store');

        Route::get('/{transfer}/print-pdf', [TransferController::class, 'printPdf'])->name('print.pdf');
        Route::delete('/{id}', [TransferController::class, 'destroy'])->name('destroy');

        Route::post('/{transfer}/cancel', [TransferController::class, 'cancel'])
            ->name('cancel')
            ->middleware('can:cancel_transfers');

        // ✅ INI ROUTE REPORT (BENAR)
        Route::get('/quality-report', [TransferQualityReportController::class, 'index'])
            ->name('quality-report.index');
    });
