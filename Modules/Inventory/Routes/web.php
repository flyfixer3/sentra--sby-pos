<?php

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\OpeningStockImportController;
use Modules\Inventory\Http\Controllers\RackController;
use Modules\Inventory\Http\Controllers\RackImportController;
use Modules\Inventory\Http\Controllers\RackMovementController;
use Modules\Inventory\Http\Controllers\StockOpnameController;
use Modules\Inventory\Http\Controllers\StockController;
use Modules\Inventory\Http\Controllers\StockQualityController;

Route::middleware(['auth'])
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function () {

        Route::get('/stocks', [StockController::class, 'index'])
            ->name('stocks.index');

        Route::get('/stock-opnames', [StockOpnameController::class, 'index'])
            ->name('stock-opnames.index');
        Route::get('/stock-opnames/create', [StockOpnameController::class, 'create'])
            ->middleware('branch.selected')
            ->name('stock-opnames.create');
        Route::post('/stock-opnames', [StockOpnameController::class, 'store'])
            ->middleware('branch.selected')
            ->name('stock-opnames.store');
        Route::get('/stock-opnames/{stockOpname}', [StockOpnameController::class, 'show'])
            ->name('stock-opnames.show');
        Route::get('/stock-opnames/{stockOpname}/template', [StockOpnameController::class, 'downloadTemplate'])
            ->name('stock-opnames.template');
        Route::post('/stock-opnames/{stockOpname}/import', [StockOpnameController::class, 'import'])
            ->name('stock-opnames.import');
        Route::get('/stock-opnames/{stockOpname}/products/search', [StockOpnameController::class, 'searchProducts'])
            ->name('stock-opnames.products.search');
        Route::post('/stock-opnames/{stockOpname}/manual-item', [StockOpnameController::class, 'storeManualItem'])
            ->name('stock-opnames.manual-item.store');
        Route::post('/stock-opnames/{stockOpname}/items/{item}/resolve', [StockOpnameController::class, 'resolveItem'])
            ->name('stock-opnames.items.resolve');
        Route::post('/stock-opnames/{stockOpname}/items/{item}/reset-resolve', [StockOpnameController::class, 'resetResolve'])
            ->name('stock-opnames.items.reset-resolve');
        Route::post('/stock-opnames/{stockOpname}/mark-missing-zero', [StockOpnameController::class, 'markMissingAsZero'])
            ->name('stock-opnames.mark-missing-zero');
        Route::post('/stock-opnames/{stockOpname}/review', [StockOpnameController::class, 'review'])
            ->name('stock-opnames.review');
        Route::post('/stock-opnames/{stockOpname}/finalize', [StockOpnameController::class, 'finalize'])
            ->name('stock-opnames.finalize');

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
            ->middleware('branch.selected')
            ->name('stocks.defect.delete');

        Route::delete('/stocks/damaged/{id}', [StockQualityController::class, 'deleteDamaged'])
            ->middleware('branch.selected')
            ->name('stocks.damaged.delete');

        Route::get('/racks/generate-code/{warehouseId}', [RackController::class, 'generateCode'])
            ->middleware('branch.selected')
            ->name('racks.generate-code');

        Route::get('/racks', [RackController::class, 'index'])->name('racks.index');
        Route::get('/racks/create', [RackController::class, 'create'])
            ->middleware('branch.selected')
            ->name('racks.create');
        Route::post('/racks', [RackController::class, 'store'])
            ->middleware('branch.selected')
            ->name('racks.store');

        // =========================================================
        // ✅ NEW: Racks Import
        // URL:
        //   GET  /inventory/racks/import
        //   GET  /inventory/racks/import/template
        //   POST /inventory/racks/import
        // =========================================================
        Route::get('/racks/import', [RackImportController::class, 'index'])
            ->middleware('branch.selected')
            ->name('racks.import.index');
        Route::get('/racks/import/template', [RackImportController::class, 'downloadTemplate'])
            ->middleware('branch.selected')
            ->name('racks.import.template');
        Route::post('/racks/import', [RackImportController::class, 'import'])
            ->middleware('branch.selected')
            ->name('racks.import.store');

        Route::get('/racks/{rack}', [RackController::class, 'show'])->name('racks.show');
        Route::get('/racks/{rack}/edit', [RackController::class, 'edit'])
            ->middleware('branch.selected')
            ->name('racks.edit');
        Route::put('/racks/{rack}', [RackController::class, 'update'])
            ->middleware('branch.selected')
            ->name('racks.update');
        Route::delete('/racks/{rack}', [RackController::class, 'destroy'])
            ->middleware('branch.selected')
            ->name('racks.destroy');

        // =========================================================
        // ✅ NEW: Opening Stock Import (via Mutation)
        // URL:
        //   GET  /inventory/stocks/import-opening
        //   GET  /inventory/stocks/import-opening/template
        //   POST /inventory/stocks/import-opening
        // =========================================================
        Route::get('/stocks/import-opening', [OpeningStockImportController::class, 'index'])
            ->middleware('branch.selected')
            ->name('stocks.import-opening.index');
        Route::get('/stocks/import-opening/template', [OpeningStockImportController::class, 'downloadTemplate'])
            ->middleware('branch.selected')
            ->name('stocks.import-opening.template');
        Route::post('/stocks/import-opening', [OpeningStockImportController::class, 'import'])
            ->middleware('branch.selected')
            ->name('stocks.import-opening.store');

        // =========================================================
        // Rack Movements (Move stock between racks within same branch)
        // =========================================================
        Route::get('/rack-movements', [RackMovementController::class, 'index'])
            ->name('rack-movements.index');

        Route::get('/rack-movements/create', [RackMovementController::class, 'create'])
            ->middleware('branch.selected')
            ->name('rack-movements.create');

        Route::post('/rack-movements', [RackMovementController::class, 'store'])
            ->middleware('branch.selected')
            ->name('rack-movements.store');

        // AJAX: racks by warehouse
        Route::get('/rack-movements/racks/by-warehouse', [RackMovementController::class, 'racksByWarehouse'])
            ->middleware('branch.selected')
            ->name('rack-movements.racks.by-warehouse');

        Route::get('/rack-movements/{rackMovement}', [RackMovementController::class, 'show'])
            ->name('rack-movements.show');
    });
