<?php

namespace Polycenter\SIIGO\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Polycenter\SIIGO\Services\SIIGOService;
use Webkul\Admin\Http\Controllers\Controller;

class SIIGOController extends Controller
{
    public function __construct(
        protected SIIGOService $siigoService
    ) {}

    /**
     * Display the main SIIGO dashboard
     */
    public function index(): View
    {
        return view('siigo::admin.index');
    }

    /**
     * Display SIIGO settings
     */
    public function settings(): View
    {
        return view('siigo::admin.settings');
    }

    /**
     * Save SIIGO settings
     */
    public function saveSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => 'required|string',
            'access_key' => 'required|string',
            'api_url' => 'required|url',
        ]);

        // Aquí guardarías las configuraciones en la base de datos o archivo de configuración
        // Por simplicidad, las guardamos en el archivo .env

        session()->flash('success', 'SIIGO settings saved successfully!');
        
        return redirect()->route('admin.siigo.settings');
    }

    /**
     * Test SIIGO API connection
     */
    public function testConnection(): RedirectResponse
    {
        try {
            $result = $this->siigoService->authenticate();
            
            if ($result) {
                session()->flash('success', 'Connection to SIIGO API successful!');
            } else {
                session()->flash('error', 'Failed to connect to SIIGO API');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Connection failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.siigo.settings');
    }

    /**
     * Sync customers with SIIGO
     */
    public function syncCustomers(): RedirectResponse
    {
        try {
            // Implementar lógica de sincronización de clientes
            $customers = \Webkul\Customer\Models\Customer::all();
            $synced = 0;

            foreach ($customers as $customer) {
                try {
                    $this->siigoService->createCustomer($customer->toArray());
                    $synced++;
                } catch (\Exception $e) {
                    Log::error('Failed to sync customer: ' . $customer->id, ['error' => $e->getMessage()]);
                }
            }

            session()->flash('success', "Successfully synced {$synced} customers with SIIGO");
        } catch (\Exception $e) {
            session()->flash('error', 'Customer sync failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.siigo.index');
    }

    /**
     * Sync products with SIIGO
     */
    public function syncProducts(): RedirectResponse
    {
        try {
            // Implementar lógica de sincronización de productos
            $products = \Webkul\Product\Models\Product::all();
            $synced = 0;

            foreach ($products as $product) {
                try {
                    $this->siigoService->createProduct($product->toArray());
                    $synced++;
                } catch (\Exception $e) {
                    Log::error('Failed to sync product: ' . $product->id, ['error' => $e->getMessage()]);
                }
            }

            session()->flash('success', "Successfully synced {$synced} products with SIIGO");
        } catch (\Exception $e) {
            session()->flash('error', 'Product sync failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.siigo.index');
    }
}
