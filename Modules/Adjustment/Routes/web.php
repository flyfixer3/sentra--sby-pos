<?php

use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'auth'], function () {

    /**
     * IMPORTANT:
     * Custom routes MUST be defined BEFORE Route::resource('adjustments', ...)
     * to avoid being captured by /adjustments/{adjustment} show route.
     */

    Route::get('adjustments/pick-units', 'AdjustmentController@pickUnits')
        ->name('adjustments.pick-units');

    Route::get('adjustments/racks', 'AdjustmentController@racks')
        ->name('adjustments.racks');

    Route::post('adjustments/quality/store', 'AdjustmentController@storeQuality')
        ->name('adjustments.quality.store');

    Route::get('adjustments/quality/products', 'AdjustmentController@qualityProducts')
        ->name('adjustments.quality.products');

    Route::resource('adjustments', 'AdjustmentController');
});