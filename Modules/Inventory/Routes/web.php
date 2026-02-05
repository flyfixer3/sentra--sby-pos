<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\RackController;
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


        Route::get('/racks/generate-code/{warehouseId}', [RackController::class, 'generateCode'])
        ->name('racks.generate-code');
        Route::get('/racks', [RackController::class, 'index'])->name('racks.index');
        Route::get('/racks/create', [RackController::class, 'create'])->name('racks.create');
        Route::post('/racks', [RackController::class, 'store'])->name('racks.store');
        Route::get('/racks/{rack}/edit', [RackController::class, 'edit'])->name('racks.edit');
        Route::put('/racks/{rack}', [RackController::class, 'update'])->name('racks.update');
        Route::delete('/racks/{rack}', [RackController::class, 'destroy'])->name('racks.destroy');
});
