<?php

namespace Modules\Inventory\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Modules\Inventory\Console\RebuildStocks;
use Modules\Inventory\Observers\MutationObserver;

class InventoryServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected string $moduleName = 'Inventory';

    /**
     * @var string $moduleNameLower
     */
    protected string $moduleNameLower = 'inventory';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        /**
         * Load basic Laravel module resources:
         * - Translations
         * - Config
         * - Views
         * - Migrations
         */
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));

        /**
         * Register available Artisan commands
         */
        $this->commands([
            RebuildStocks::class,
        ]);

        /**
         * Listen for model Mutation created event,
         * and automatically update stock via MutationObserver.
         */
        $this->registerMutationListener();
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register configuration file for module.
     *
     * @return void
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    /**
     * Register views for module.
     *
     * @return void
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath,
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations for module.
     *
     * @return void
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Register event listener for model Mutation.
     *
     * Every time a new Mutation is created, this listener
     * will trigger the MutationObserver to adjust stocks automatically.
     *
     * NOTE:
     * Ensure that your Mutation model namespace is App\Models\Mutation
     */
    protected function registerMutationListener(): void
    {
        $mutationClass = \App\Models\Mutation::class;

        Event::listen("eloquent.created: {$mutationClass}", function ($event, $payload) {
            $mutation = $payload[0] ?? null;

            if (!$mutation) {
                return;
            }

            /** @var MutationObserver $observer */
            $observer = app(MutationObserver::class);
            $observer->handleCreated($mutation);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Helper: get publishable view paths.
     *
     * @return array
     */
    private function getPublishableViewPaths(): array
    {
        $paths = [];

        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }

        return $paths;
    }
}
