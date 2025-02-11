<?php

use Modules\PurchaseDelivery\Http\Controllers\PurchaseDeliveryController;

Route::prefix('purchase-delivery')->group(function () {
    Route::get('/', [PurchaseDeliveryController::class, 'index'])
        ->name('purchase-deliveries.index')
        ->middleware('can:access_purchase_deliveries');

    Route::get('/create/{purchaseOrder}', [PurchaseDeliveryController::class, 'create'])
        ->name('purchase-deliveries.create')
        ->middleware('can:create_purchase_deliveries');

    Route::post('/store', [PurchaseDeliveryController::class, 'store'])
        ->name('purchase-deliveries.store')
        ->middleware('can:create_purchase_deliveries');

    Route::get('/{purchaseDelivery}', [PurchaseDeliveryController::class, 'show'])
        ->name('purchase-deliveries.show')
        ->middleware('can:show_purchase_deliveries');

        Route::delete('/{id}', [PurchaseDeliveryController::class, 'destroy'])
        ->name('purchase-deliveries.destroy')
        ->middleware('can:delete_purchase_deliveries');    

    // ✅ Add Edit & Update Routes (if needed)
    Route::get('/{purchaseDelivery}/edit', [PurchaseDeliveryController::class, 'edit'])
        ->name('purchase-deliveries.edit')
        ->middleware('can:edit_purchase_deliveries');

    Route::put('/{purchaseDelivery}', [PurchaseDeliveryController::class, 'update'])
        ->name('purchase-deliveries.update')
        ->middleware('can:edit_purchase_deliveries');

    Route::post('purchase-deliveries/bulk-delete', [PurchaseDeliveryController::class, 'bulkDelete'])
    ->name('purchase-deliveries.bulk-delete')
    ->middleware('can:delete_purchase_deliveries');

});
