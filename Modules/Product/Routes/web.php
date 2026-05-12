<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
use Modules\Product\Http\Controllers\WarehousesController;
use Modules\Product\Http\Controllers\HppLedgerController;
use Modules\Product\Http\Controllers\ProductImportController;
use Modules\Product\Http\Controllers\DefectTypeController;
use Modules\Product\Http\Controllers\AccessoriesController;
use Modules\Product\Http\Controllers\BarcodeController;
use Modules\Product\Entities\Product;

Route::bind('product', function ($value) {
    return Product::withoutGlobalScopes()->findOrFail($value);
});

Route::middleware(['auth'])->prefix('warehouses')->group(function () {
    Route::get('{id}/preview', [WarehousesController::class, 'preview'])->name('warehouses.preview');
});

Route::group(['middleware' => 'auth'], function () {
    Route::get('/hpp-ledger', [HppLedgerController::class, 'index'])->name('hpp-ledger.index');
    Route::get('/defect-types', [DefectTypeController::class, 'index'])->name('defect-types.index');
    Route::post('/defect-types', [DefectTypeController::class, 'store'])->name('defect-types.store');
    Route::delete('/defect-types', [DefectTypeController::class, 'destroy'])->name('defect-types.destroy');
    Route::get('/products/import', [ProductImportController::class, 'index'])->name('products.import.index');
    Route::get('/products/import/template', [ProductImportController::class, 'downloadTemplate'])->name('products.import.template');
    Route::post('/products/import', [ProductImportController::class, 'import'])->name('products.import.store');

    //Print Barcode
    Route::get('/products/print-barcode', [BarcodeController::class, 'printBarcode'])
        ->middleware('branch.selected')
        ->name('barcode.print');
    Route::get('/products/{product}/labels/good', [BarcodeController::class, 'printGoodLabel'])->name('products.labels.good');
    Route::get('/inventory/labels/defect/{id}', [BarcodeController::class, 'printDefectLabel'])
        ->middleware('branch.selected')
        ->name('inventory.labels.defect');
    Route::get('/inventory/labels/damaged/{id}', [BarcodeController::class, 'printDamagedLabel'])
        ->middleware('branch.selected')
        ->name('inventory.labels.damaged');
    //Product
    Route::resource('products', 'ProductController');
    Route::get('/product-accessories/import/template', [AccessoriesController::class, 'downloadTemplate'])->name('product-accessories.import.template');
    Route::post('/product-accessories/import', [AccessoriesController::class, 'import'])->name('product-accessories.import.store');
    //Product Category
    Route::resource('product-accessories', 'AccessoriesController')->except('create', 'show');
    Route::resource('product-categories', 'CategoriesController')->except('create', 'show');
    Route::resource('product-warehouses', 'WarehousesController')->except('create', 'show');
});
