<?php

use Illuminate\Support\Facades\Route;
use Modules\Adjustment\Http\Controllers\AdjustmentController;

Route::group(['middleware' => 'auth'], function () {

    /**
     * IMPORTANT:
     * Custom routes MUST be defined BEFORE Route::resource('adjustments', ...)
     * to avoid being captured by /adjustments/{adjustment} show route.
     */

    // ✅ NEW: pick specific unit IDs (SUB adjustment modal)
    Route::get('adjustments/pick-units', [AdjustmentController::class, 'pickUnits'])
        ->name('adjustments.pick-units');

    // ✅ NEW: get racks by warehouse
    Route::get('adjustments/racks', [AdjustmentController::class, 'racks'])
        ->name('adjustments.racks');

    // Quality Reclass (store)
    Route::post('adjustments/quality/store', [AdjustmentController::class, 'storeQuality'])
        ->name('adjustments.quality.store');

    // Quality - load products by selected warehouse (GOOD stock only)
    Route::get('adjustments/quality/products', [AdjustmentController::class, 'qualityProducts'])
        ->name('adjustments.quality.products');

    // Default CRUD (put at bottom)
    Route::resource('adjustments', AdjustmentController::class);
});