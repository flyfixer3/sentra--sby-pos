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
        ->middleware('branch.selected')
        ->name('adjustments.quality_to_good.picker_data');

    Route::get('adjustments/racks', 'AdjustmentController@racks')
        ->middleware('branch.selected')
        ->name('adjustments.racks');

    Route::post('adjustments/quality/store', 'AdjustmentController@storeQuality')
        ->middleware('branch.selected')
        ->name('adjustments.quality.store');

    Route::get('adjustments/quality/products', 'AdjustmentController@qualityProducts')
        ->middleware('branch.selected')
        ->name('adjustments.quality.products');

    Route::get('adjustments/stock-sub/picker-data', 'AdjustmentController@stockSubPickerData')
        ->middleware('branch.selected')
        ->name('adjustments.stock_sub.picker_data');

    Route::get('/adjustments/quality/to-good/picker', [\Modules\Adjustment\Http\Controllers\AdjustmentController::class, 'qualityToGoodPicker'])
        ->middleware('branch.selected')
        ->name('adjustments.quality.to_good.picker');

    Route::middleware('branch.selected')->group(function () {
        Route::get('adjustments/create', 'AdjustmentController@create')
            ->name('adjustments.create');
        Route::post('adjustments', 'AdjustmentController@store')
            ->name('adjustments.store');
        Route::get('adjustments/{adjustment}/edit', 'AdjustmentController@edit')
            ->name('adjustments.edit');
        Route::match(['put', 'patch'], 'adjustments/{adjustment}', 'AdjustmentController@update')
            ->name('adjustments.update');
        Route::delete('adjustments/{adjustment}', 'AdjustmentController@destroy')
            ->name('adjustments.destroy');
    });

    Route::get('adjustments', 'AdjustmentController@index')
        ->name('adjustments.index');
    Route::get('adjustments/{adjustment}', 'AdjustmentController@show')
        ->name('adjustments.show');
});