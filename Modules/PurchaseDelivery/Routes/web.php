<?php

use Illuminate\Support\Facades\Route;
use Modules\PurchaseDelivery\Http\Controllers\PurchaseDeliveryController;

Route::middleware(['auth'])->group(function () {

    Route::prefix('purchase-deliveries')->group(function () {

        Route::get('/', [PurchaseDeliveryController::class, 'index'])
            ->name('purchase-deliveries.index')
            ->middleware('can:access_purchase_deliveries');

        // CREATE PD harus dari PO
        Route::get('/create/from-po/{purchaseOrder}', [PurchaseDeliveryController::class, 'create'])
            ->name('purchase-orders.deliveries.create')
            ->middleware(['branch.selected', 'can:create_purchase_deliveries']);

        Route::post('/', [PurchaseDeliveryController::class, 'store'])
            ->name('purchase-deliveries.store')
            ->middleware(['branch.selected', 'can:create_purchase_deliveries']);

        Route::post('/bulk-delete', [PurchaseDeliveryController::class, 'bulkDelete'])
            ->name('purchase-deliveries.bulk-delete')
            ->middleware(['branch.selected', 'can:delete_purchase_deliveries']);

        // ✅ CONFIRM
        Route::get('/{purchaseDelivery}/confirm', [PurchaseDeliveryController::class, 'confirm'])
            ->name('purchase-deliveries.confirm')
            ->middleware(['branch.selected', 'can:confirm_purchase_deliveries']);

        Route::post('/{purchaseDelivery}/confirm', [PurchaseDeliveryController::class, 'confirmStore'])
            ->name('purchase-deliveries.confirm.store')
            ->middleware(['branch.selected', 'can:confirm_purchase_deliveries']);

        Route::get('/{purchaseDelivery}/edit', [PurchaseDeliveryController::class, 'edit'])
            ->name('purchase-deliveries.edit')
            ->middleware(['branch.selected', 'can:edit_purchase_deliveries']);

        Route::put('/{purchaseDelivery}', [PurchaseDeliveryController::class, 'update'])
            ->name('purchase-deliveries.update')
            ->middleware(['branch.selected', 'can:edit_purchase_deliveries']);

        Route::delete('/{purchaseDelivery}', [PurchaseDeliveryController::class, 'destroy'])
            ->name('purchase-deliveries.destroy')
            ->middleware(['branch.selected', 'can:delete_purchase_deliveries']);

        Route::get('/{purchaseDelivery}', [PurchaseDeliveryController::class, 'show'])
            ->name('purchase-deliveries.show')
            ->middleware('can:show_purchase_deliveries');
    });
});
