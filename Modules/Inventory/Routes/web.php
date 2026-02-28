<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\RackController;
use Modules\Inventory\Http\Controllers\RackImportController;
use Modules\Inventory\Http\Controllers\RackMovementController;
use Modules\Inventory\Http\Controllers\StockController;
use Modules\Inventory\Http\Controllers\StockQualityController;

Route::middleware(['auth'])
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function () {

        Route::get('/stocks', [StockController::class, 'index'])
            ->name('stocks.index');

        /**
         * ✅ NEW: Stock Detail Modal (options + data)
         */
        Route::get('/stocks/detail/options', [StockController::class, 'detailOptions'])
            ->name('stocks.detail.options');

        Route::get('/stocks/detail/data', [StockController::class, 'detailData'])
            ->name('stocks.detail.data');

        /**
         * ✅ NEW: Quality Modal Options (warehouse/rack filter)
         */
        Route::get('/stocks/quality/options', [StockController::class, 'qualityOptions'])
            ->name('stocks.quality.options');

        /**
         * ✅ Existing: Quality Details (defect/damaged)
         */
        Route::get('/stocks/quality-details/{type}/{productId}', [StockController::class, 'qualityDetails'])
            ->name('stocks.quality-details');

        /**
         * (Optional) Existing old rack detail endpoint
         * Kalau UI baru sudah pakai Stock Detail modal, ini bisa kamu hapus nanti.
         */
        Route::get('/stocks/rack-details/{productId}/{branchId}/{warehouseId}', [StockController::class, 'rackDetails'])
            ->name('stocks.rack-details');

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

        // =========================================================
        // ✅ NEW: Racks Import
        // URL:
        //   GET  /inventory/racks/import
        //   GET  /inventory/racks/import/template
        //   POST /inventory/racks/import
        // =========================================================
        Route::get('/racks/import', [RackImportController::class, 'index'])->name('racks.import.index');
        Route::get('/racks/import/template', [RackImportController::class, 'downloadTemplate'])->name('racks.import.template');
        Route::post('/racks/import', [RackImportController::class, 'import'])->name('racks.import.store');

        // =========================================================
        // ✅ NEW: Opening Stock Import (via Mutation)
        // URL:
        //   GET  /inventory/stocks/import-opening
        //   GET  /inventory/stocks/import-opening/template
        //   POST /inventory/stocks/import-opening
        // =========================================================
        Route::get('/stocks/import-opening', [OpeningStockImportController::class, 'index'])->name('stocks.import-opening.index');
        Route::get('/stocks/import-opening/template', [OpeningStockImportController::class, 'downloadTemplate'])->name('stocks.import-opening.template');
        Route::post('/stocks/import-opening', [OpeningStockImportController::class, 'import'])->name('stocks.import-opening.store');

        // =========================================================
        // Rack Movements (Move stock between racks within same branch)
        // =========================================================
        Route::get('/rack-movements', [RackMovementController::class, 'index'])
            ->name('rack-movements.index');

        Route::get('/rack-movements/create', [RackMovementController::class, 'create'])
            ->name('rack-movements.create');

        Route::post('/rack-movements', [RackMovementController::class, 'store'])
            ->name('rack-movements.store');

        Route::get('/rack-movements/{rackMovement}', [RackMovementController::class, 'show'])
            ->name('rack-movements.show');

        // AJAX: racks by warehouse
        Route::get('/rack-movements/racks/by-warehouse', [RackMovementController::class, 'racksByWarehouse'])
            ->name('rack-movements.racks.by-warehouse');
    });
