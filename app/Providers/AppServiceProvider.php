<?php

namespace App\Providers;

use App\Http\Livewire\Adjustment\ProductTable;
use App\Http\Livewire\Adjustment\ProductTableQualityToGood;
use App\Http\Livewire\Adjustment\ProductTableStockSub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

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
        // Model::preventLazyLoading(!app()->isProduction());
    }
}
