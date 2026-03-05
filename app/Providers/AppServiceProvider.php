<?php

namespace App\Providers;

use App\Http\Livewire\Adjustment\ProductTable;
use App\Http\Livewire\Adjustment\ProductTableQualityToGood;
use App\Http\Livewire\Adjustment\ProductTableStockSub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Modules\Quotation\Observers\SaleDeliveryQuotationSyncObserver;
use Modules\Quotation\Observers\SaleOrderQuotationSyncObserver;
use Modules\SaleDelivery\Entities\SaleDelivery;
use Modules\SaleOrder\Entities\SaleOrder;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Livewire::component('adjustment.product-table-stock', ProductTable::class);
        Livewire::component('adjustment.product-table-quality', ProductTable::class);
        Livewire::component('adjustment.product-table-stock-sub', ProductTableStockSub::class);
        Livewire::component('adjustment.product-table-quality-to-good', ProductTableQualityToGood::class);
        Paginator::useBootstrap();
        // Model::preventLazyLoading(!app()->isProduction());
        // Observer sync status quotation saat SO/SD dibuat atau dihapus (soft delete)
        // Ini aman karena tidak mengubah alur existing destroy controller.
        try {
            SaleOrder::observe(SaleOrderQuotationSyncObserver::class);
            SaleDelivery::observe(SaleDeliveryQuotationSyncObserver::class);
        } catch (\Throwable $e) {
            // Kalau pada environment tertentu module belum ke-load, jangan bikin aplikasi crash.
            // (misal: artisan cache, config:cache, atau unit test minimal)
        }
    }
}
