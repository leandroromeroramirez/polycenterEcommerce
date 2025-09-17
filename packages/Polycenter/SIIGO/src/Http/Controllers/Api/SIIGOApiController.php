<?php

namespace Polycenter\SIIGO\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Polycenter\SIIGO\Services\SIIGOService;
use App\Http\Controllers\Controller;

class SIIGOApiController extends Controller
{
    public function __construct(
        protected SIIGOService $siigoService
    ) {}

    /**
     * Handle SIIGO webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            // Validar webhook signature si está configurado
            $secret = config('siigo.webhook.secret');
            if ($secret) {
                $signature = $request->header('X-SIIGO-Signature');
                $payload = $request->getContent();
                $expectedSignature = hash_hmac('sha256', $payload, $secret);
                
                if (!hash_equals($expectedSignature, $signature)) {
                    return response()->json(['error' => 'Invalid signature'], 401);
                }
            }

            $data = $request->all();
            
            // Procesar el webhook según el tipo de evento
            $eventType = $data['event_type'] ?? null;
            
            switch ($eventType) {
                case 'customer.created':
                    $this->handleCustomerCreated($data);
                    break;
                case 'customer.updated':
                    $this->handleCustomerUpdated($data);
                    break;
                case 'invoice.created':
                    $this->handleInvoiceCreated($data);
                    break;
                default:
                    Log::info('Unhandled SIIGO webhook event', ['event_type' => $eventType, 'data' => $data]);
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('SIIGO webhook error', ['error' => $e->getMessage(), 'request' => $request->all()]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Create customer in SIIGO
     */
    public function createCustomer(Request $request): JsonResponse
    {
        try {
            $customerData = $request->validate([
                'identification' => 'required|string',
                'name' => 'required|string',
                'email' => 'required|email',
                'phone' => 'string',
                'address' => 'string',
            ]);

            $result = $this->siigoService->createCustomer($customerData);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Update customer in SIIGO
     */
    public function updateCustomer(Request $request, string $id): JsonResponse
    {
        try {
            $customerData = $request->validate([
                'name' => 'string',
                'email' => 'email',
                'phone' => 'string',
                'address' => 'string',
            ]);

            $result = $this->siigoService->updateCustomer($id, $customerData);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Create product in SIIGO
     */
    public function createProduct(Request $request): JsonResponse
    {
        try {
            $productData = $request->validate([
                'code' => 'required|string',
                'name' => 'required|string',
                'description' => 'string',
                'price' => 'required|numeric',
            ]);

            $result = $this->siigoService->createProduct($productData);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Create invoice in SIIGO
     */
    public function createInvoice(Request $request): JsonResponse
    {
        try {
            $invoiceData = $request->validate([
                'customer_id' => 'required|string',
                'items' => 'required|array',
                'items.*.product_id' => 'required|string',
                'items.*.quantity' => 'required|numeric',
                'items.*.price' => 'required|numeric',
            ]);

            $result = $this->siigoService->createInvoice($invoiceData);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Handle customer created webhook
     */
    protected function handleCustomerCreated(array $data): void
    {
        // Implementar lógica para manejar cliente creado en SIIGO
        Log::info('Customer created in SIIGO', $data);
    }

    /**
     * Handle customer updated webhook
     */
    protected function handleCustomerUpdated(array $data): void
    {
        // Implementar lógica para manejar cliente actualizado en SIIGO
        Log::info('Customer updated in SIIGO', $data);
    }

    /**
     * Handle invoice created webhook
     */
    protected function handleInvoiceCreated(array $data): void
    {
        // Implementar lógica para manejar factura creada en SIIGO
        Log::info('Invoice created in SIIGO', $data);
    }
}
