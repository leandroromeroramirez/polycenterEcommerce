<?php

namespace Polycenter\MessagingShipping\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Polycenter\MessagingShipping\Services\MessagingShippingService;
use Polycenter\MessagingShipping\Services\Adapters\EnviaAdapter;
use Polycenter\MessagingShipping\Services\ShippingCalculatorService;
use Polycenter\MessagingShipping\Services\OrderShippingService;
use Polycenter\MessagingShipping\Listeners\OrderPlacedListener;
use Polycenter\MessagingShipping\Carriers\MessagingShippingCarrier;
use Polycenter\MessagingShipping\Console\Commands\TestEnviaConnectionCommand;

class MessagingShippingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../Http/admin-routes.php');
        $this->loadRoutesFrom(__DIR__ . '/../Http/api-routes.php');
        
        // Load views
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'messaging-shipping');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        
        // Load translations
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'messaging-shipping');
        
        // Publish configuration
        $this->publishes([
            __DIR__ . '/../Config/messaging-shipping.php' => config_path('messaging-shipping.php'),
        ], 'messaging-shipping-config');
        
        // Publish views
        $this->publishes([
            __DIR__ . '/../Resources/views' => resource_path('views/vendor/messaging-shipping'),
        ], 'messaging-shipping-views');
        
        // Publish translations
        $this->publishes([
            __DIR__ . '/../Resources/lang' => resource_path('lang/vendor/messaging-shipping'),
        ], 'messaging-shipping-lang');
        
        // Register event listeners
        $this->registerEventListeners();
        
        // Register shipping methods
        $this->registerShippingMethods();
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__.'/../Config/messaging-shipping.php',
            'messaging-shipping'
        );
        
        // Merge system configuration for admin panel
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/system.php',
            'core.system'
        );

        // Register main service as singleton
        $this->app->singleton('messaging-shipping', function ($app) {
            return new MessagingShippingService();
        });

        // Register EnviaAdapter as singleton
        $this->app->singleton(EnviaAdapter::class, function ($app) {
            return new EnviaAdapter();
        });

        // Register services
        $this->app->bind(MessagingShippingService::class, function ($app) {
            return $app->make('messaging-shipping');
        });

        // Register other services
        $this->app->bind(ShippingCalculatorService::class);
        $this->app->bind(OrderShippingService::class);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestEnviaConnectionCommand::class,
            ]);
        }
    }
    
    /**
     * Register event listeners
     */
    protected function registerEventListeners(): void
    {
        // Listen for order placement events in Bagisto
        Event::listen('checkout.order.save.after', [OrderPlacedListener::class, 'handle']);
        Event::listen('sales.order.save.after', [OrderPlacedListener::class, 'handle']);
    }
    
    /**
     * Register shipping methods in Bagisto
     */
    protected function registerShippingMethods(): void
    {
        // Register MessagingShipping carrier with Bagisto
        $carriers = config('carriers', []);
        
        $carriers['messaging_shipping'] = [
            'code' => 'messaging_shipping',
            'title' => 'MessagingShipping',
            'description' => 'Envíos rápidos y seguros con Envia.com',
            'active' => true,
            'class' => MessagingShippingCarrier::class,
            'sort_order' => 1,
            
            // Configuration options that will appear in admin
            'origin_city_code' => '11001',
            'origin_postal_code' => '110111',
            'origin_city' => 'Bogotá',
            'origin_state' => 'Bogotá D.C.',
            'origin_country' => 'CO',
            'default_length' => 30,
            'default_width' => 20,
            'default_height' => 15,
        ];
        
        $this->app['config']->set('carriers', $carriers);
        
        // Also register in sales.carriers for admin configuration
        $salesCarriers = config('sales.carriers', []);
        
        $salesCarriers['messaging_shipping'] = [
            'title' => 'MessagingShipping',
            'description' => 'Envíos rápidos y seguros con Envia.com',
            'active' => true,
            'class' => MessagingShippingCarrier::class,
            'sort_order' => 1,
        ];
        
        $this->app['config']->set('sales.carriers', $salesCarriers);
    }
}
