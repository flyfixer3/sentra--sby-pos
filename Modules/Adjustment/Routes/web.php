<?php

use Illuminate\Support\Facades\Route;
use Modules\Adjustment\Http\Controllers\AdjustmentController;

Route::group(['middleware' => 'auth'], function () {

    // Default CRUD
    Route::resource('adjustments', 'AdjustmentController');

    // Quality Reclass (store)
    Route::post('adjustments/quality/store', 'AdjustmentController@storeQuality')
        ->name('adjustments.quality.store');

    // Quality - load products by selected warehouse (GOOD stock only)
    Route::get('adjustments/quality/products', 'AdjustmentController@qualityProducts')
        ->name('adjustments.quality.products');

    // ✅ NEW: get racks by warehouse
    Route::get('adjustments/racks', 'AdjustmentController@racks')
        ->name('adjustments.racks');

    Route::get('/adjustments/pick-units', [AdjustmentController::class, 'pickUnits'])
    ->name('adjustments.pick-units');
});
