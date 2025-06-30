<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
use Modules\Product\Http\Controllers\WarehousesController;

Route::middleware(['auth'])->prefix('warehouses')->group(function () {
    Route::get('{id}/preview', [WarehousesController::class, 'preview'])->name('warehouses.preview');
});

Route::group(['middleware' => 'auth'], function () {
    //Print Barcode
    Route::get('/products/print-barcode', 'BarcodeController@printBarcode')->name('barcode.print');
    //Product
    Route::resource('products', 'ProductController');
    //Product Category
    Route::resource('product-accessories', 'AccessoriesController')->except('create', 'show');
    Route::resource('product-categories', 'CategoriesController')->except('create', 'show');
    Route::resource('product-warehouses', 'WarehousesController')->except('create', 'show');
});

