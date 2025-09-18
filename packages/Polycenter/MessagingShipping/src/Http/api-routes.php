<?php

use Illuminate\Support\Facades\Route;
use Polycenter\MessagingShipping\Http\Controllers\Api\MessagingShippingApiController;

Route::group([
    'prefix' => 'api/v1/messaging-shipping',
    'middleware' => ['api'],
], function () {
    
    // Public API endpoints (no authentication required)
    Route::get('/test-connection', [MessagingShippingApiController::class, 'testConnection'])
        ->name('api.messaging-shipping.test-connection');
    
    Route::post('/shipping-rates', [MessagingShippingApiController::class, 'getShippingRates'])
        ->name('api.messaging-shipping.rates');
    
    Route::get('/tracking/{trackingNumber}', [MessagingShippingApiController::class, 'getTrackingInfo'])
        ->name('api.messaging-shipping.tracking');
    
    // Webhook endpoint
    Route::post('/webhook', [MessagingShippingApiController::class, 'webhook'])
        ->name('api.messaging-shipping.webhook');
    
    // Protected API endpoints (require authentication)
    Route::middleware(['auth:sanctum'])->group(function () {
        
        Route::post('/shipping-orders', [MessagingShippingApiController::class, 'createShippingOrder'])
            ->name('api.messaging-shipping.create-order');
        
        Route::get('/shipping-orders/{shippingOrderId}', [MessagingShippingApiController::class, 'getShippingOrderStatus'])
            ->name('api.messaging-shipping.order-status');
        
        Route::post('/shipping-orders/{shippingOrderId}/cancel', [MessagingShippingApiController::class, 'cancelShippingOrder'])
            ->name('api.messaging-shipping.cancel-order');
    });
});
