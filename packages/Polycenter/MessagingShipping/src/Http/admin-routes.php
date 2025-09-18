<?php

use Illuminate\Support\Facades\Route;
use Polycenter\MessagingShipping\Http\Controllers\MessagingShippingController;

Route::group([
    'prefix' => config('app.admin_url', 'admin'),
    'middleware' => ['web', 'admin'],
], function () {
    
    Route::prefix('messaging-shipping')->name('admin.messaging-shipping.')->group(function () {
        
        // Dashboard
        Route::get('/', [MessagingShippingController::class, 'index'])
            ->name('index');
        
        // Settings
        Route::get('/settings', [MessagingShippingController::class, 'settings'])
            ->name('settings');
        
        Route::post('/settings', [MessagingShippingController::class, 'saveSettings'])
            ->name('settings.save');
        
        // Test connection
        Route::post('/test-connection', [MessagingShippingController::class, 'testConnection'])
            ->name('test-connection');
        
        // Shipping orders
        Route::get('/orders/{shippingOrder}', [MessagingShippingController::class, 'show'])
            ->name('show');
        
        Route::post('/orders/{shippingOrder}/cancel', [MessagingShippingController::class, 'cancel'])
            ->name('cancel');
        
        Route::post('/orders/{shippingOrder}/refresh-status', [MessagingShippingController::class, 'refreshStatus'])
            ->name('refresh-status');
        
        // Bulk actions
        Route::post('/bulk-action', [MessagingShippingController::class, 'bulkAction'])
            ->name('bulk-action');
    });
});
