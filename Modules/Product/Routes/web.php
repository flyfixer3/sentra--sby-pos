<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
use Modules\Product\Http\Controllers\WarehousesController;
use Modules\Product\Http\Controllers\ProductImportController;

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

    // =========================================================
    // ✅ NEW: Products Import (Template + Upload)
    // URL:
    //   GET  /products/import
    //   GET  /products/import/template
    //   POST /products/import
    // =========================================================
    Route::get('/products/import', [ProductImportController::class, 'index'])->name('products.import.index');
    Route::get('/products/import/template', [ProductImportController::class, 'downloadTemplate'])->name('products.import.template');
    Route::post('/products/import', [ProductImportController::class, 'import'])->name('products.import.store');
});