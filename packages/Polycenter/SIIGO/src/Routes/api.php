<?php

use Illuminate\Support\Facades\Route;
use Polycenter\SIIGO\Http\Controllers\Api\SIIGOApiController;

/*
|--------------------------------------------------------------------------
| SIIGO API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('api/siigo')->middleware(['api'])->group(function () {
    Route::post('/webhook', [SIIGOApiController::class, 'webhook'])->name('api.siigo.webhook');
    Route::post('/customers', [SIIGOApiController::class, 'createCustomer'])->name('api.siigo.customers.create');
    Route::put('/customers/{id}', [SIIGOApiController::class, 'updateCustomer'])->name('api.siigo.customers.update');
    Route::post('/products', [SIIGOApiController::class, 'createProduct'])->name('api.siigo.products.create');
    Route::post('/invoices', [SIIGOApiController::class, 'createInvoice'])->name('api.siigo.invoices.create');
});
