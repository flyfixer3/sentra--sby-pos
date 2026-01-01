<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\StockController;
use Modules\Inventory\Http\Controllers\StockQualityController;

Route::middleware(['auth'])
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function () {

        Route::get('/stocks', [StockController::class, 'index'])
            ->name('stocks.index');

        Route::get('/stocks/rack-details/{productId}/{branchId}/{warehouseId}', [StockController::class, 'rackDetails'])
            ->name('stocks.rack-details');

        Route::get('/stocks/quality-details/{type}/{productId}', [StockController::class, 'qualityDetails'])
            ->name('stocks.quality-details');

        // ✅ DELETE endpoint (jual defect/damaged)
        Route::delete('/stocks/defect/{id}', [StockQualityController::class, 'deleteDefect'])
            ->name('stocks.defect.delete');

        Route::delete('/stocks/damaged/{id}', [StockQualityController::class, 'deleteDamaged'])
            ->name('stocks.damaged.delete');
    });
