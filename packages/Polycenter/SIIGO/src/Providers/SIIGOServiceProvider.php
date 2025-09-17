<?php

namespace Polycenter\SIIGO\Providers;

use Illuminate\Support\ServiceProvider;
use Polycenter\SIIGO\Services\SIIGOService;

class SIIGOServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Registrar rutas
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        // Registrar vistas
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'siigo');

        // Registrar migraciones
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Registrar traducciones
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'siigo');

        // Publicar archivos de configuración
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../Config/siigo.php' => config_path('siigo.php'),
            ], 'siigo-config');

            $this->publishes([
                __DIR__ . '/../Resources/views' => resource_path('views/vendor/siigo'),
            ], 'siigo-views');

            $this->publishes([
                __DIR__ . '/../Resources/lang' => resource_path('lang/vendor/siigo'),
            ], 'siigo-lang');
        }
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        // Registrar archivo de configuración
        $this->mergeConfigFrom(__DIR__ . '/../Config/siigo.php', 'siigo');

        // Registrar el servicio SIIGO
        $this->app->singleton(SIIGOService::class, function ($app) {
            return new SIIGOService();
        });

        // Registrar alias
        $this->app->alias(SIIGOService::class, 'siigo');
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            SIIGOService::class,
        ];
    }
}
