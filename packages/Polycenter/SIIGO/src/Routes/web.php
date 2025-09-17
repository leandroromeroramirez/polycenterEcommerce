<?php

use Illuminate\Support\Facades\Route;
use Polycenter\SIIGO\Http\Controllers\SIIGOController;

/*
|--------------------------------------------------------------------------
| SIIGO Web Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin/siigo')->middleware(['web', 'admin'])->group(function () {
    Route::get('/', [SIIGOController::class, 'index'])->name('admin.siigo.index');
    Route::get('/settings', [SIIGOController::class, 'settings'])->name('admin.siigo.settings');
    Route::post('/settings', [SIIGOController::class, 'saveSettings'])->name('admin.siigo.settings.save');
    Route::post('/test-connection', [SIIGOController::class, 'testConnection'])->name('admin.siigo.test');
    Route::post('/sync-customers', [SIIGOController::class, 'syncCustomers'])->name('admin.siigo.sync.customers');
    Route::post('/sync-products', [SIIGOController::class, 'syncProducts'])->name('admin.siigo.sync.products');
});
