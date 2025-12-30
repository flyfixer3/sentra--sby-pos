<?php

/*
|------------------------------------------------------------------------------
| Web Routes – Module Inventory
|------------------------------------------------------------------------------
| Semua route Inventory diproteksi auth dan menggunakan prefix + name "inventory."
| Pola sama seperti Module Product yang kamu pakai.
|------------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\StockController;

Route::middleware(['auth'])
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function () {
        // Stocks index (daftar stok / ringkasan stok)
        Route::get('/stocks', [StockController::class, 'index'])
            ->name('stocks.index');

        // Detail stok per RAK untuk 1 produk pada cabang & gudang tertentu
        Route::get(
            '/stocks/rack-details/{productId}/{branchId}/{warehouseId}',
            [StockController::class, 'rackDetails']
        )->name('stocks.rack-details');
        Route::get(
            '/stocks/quality-details/{type}/{productId}',
            [StockController::class, 'qualityDetails']
        )->name('stocks.quality-details');
    }
);
