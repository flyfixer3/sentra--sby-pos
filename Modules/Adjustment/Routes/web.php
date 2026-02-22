<?php

use Illuminate\Support\Facades\Route;
use Modules\Adjustment\Http\Controllers\AdjustmentController;

Route::group(['middleware' => 'auth'], function () {

    /**
     * IMPORTANT:
     * Custom routes MUST be defined BEFORE Route::resource('adjustments', ...)
     * to avoid being captured by /adjustments/{adjustment} show route.
     */


    Route::get('adjustments/quality-to-good/picker-data', 'AdjustmentController@qualityToGoodPickerData')
        ->name('adjustments.quality_to_good.picker_data');

    Route::get('adjustments/racks', 'AdjustmentController@racks')
        ->name('adjustments.racks');

    Route::post('adjustments/quality/store', 'AdjustmentController@storeQuality')
        ->name('adjustments.quality.store');

    Route::get('adjustments/quality/products', 'AdjustmentController@qualityProducts')
        ->name('adjustments.quality.products');

    Route::get('adjustments/stock-sub/picker-data', 'AdjustmentController@stockSubPickerData')
        ->name('adjustments.stock_sub.picker_data');

    Route::resource('adjustments', 'AdjustmentController');


    Route::middleware(['auth'])->group(function () {
        Route::get('adjustments/quality/to-good/picker', [AdjustmentController::class, 'qualityToGoodPicker'])
            ->name('adjustments.quality.to_good.picker');
    });
});