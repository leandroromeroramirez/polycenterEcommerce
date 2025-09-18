<?php

namespace Polycenter\MessagingShipping\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Polycenter\MessagingShipping\Services\MessagingShippingService;
use Polycenter\MessagingShipping\Services\ShippingCalculatorService;
use Polycenter\MessagingShipping\Services\OrderShippingService;
use Polycenter\MessagingShipping\Models\ShippingOrder;
use App\Http\Controllers\Controller;

class MessagingShippingApiController extends Controller
{
    public function __construct(
        protected MessagingShippingService $messagingService,
        protected ShippingCalculatorService $calculatorService,
        protected OrderShippingService $orderShippingService
    ) {}

    /**
     * Test connection with the shipping provider
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->messagingService->testConnection();
            
            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('[MessagingShipping API] Test connection failed', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Connection test failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get shipping rates for given parameters
     */
    public function getShippingRates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'origin' => 'required|array',
            'origin.city_code' => 'required|string',
            'origin.postal_code' => 'required|string',
            'destination' => 'required|array',
            'destination.city_code' => 'required|string',
            'destination.postal_code' => 'required|string',
            'packages' => 'required|array|min:1',
            'packages.*.weight' => 'required|numeric|min:0.1',
            'packages.*.height' => 'required|numeric|min:1',
            'packages.*.width' => 'required|numeric|min:1',
            'packages.*.length' => 'required|numeric|min:1',
            'packages.*.declared_value' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Create shipping data structure for the service
            $shippingData = [
                'origin' => $request->origin,
                'destination' => $request->destination,
                'packages' => $request->packages,
            ];
            
            $result = $this->messagingService->getShippingRates($shippingData);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get shipping rates via API', [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate shipping rates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create shipping order
     */
    public function createShippingOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'service_type' => 'required|string',
            'shipping_cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = \Webkul\Sales\Models\Order::findOrFail($request->order_id);
            
            $shippingOrder = $this->orderShippingService->createShippingOrder(
                $order,
                $request->service_type,
                $request->shipping_cost,
                $request->notes
            );

            return response()->json([
                'success' => true,
                'message' => 'Shipping order created successfully',
                'data' => [
                    'shipping_order_id' => $shippingOrder->id,
                    'api_order_id' => $shippingOrder->api_order_id,
                    'tracking_number' => $shippingOrder->tracking_number,
                    'status' => $shippingOrder->status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create shipping order via API', [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create shipping order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get shipping order status
     */
    public function getShippingOrderStatus(Request $request, $shippingOrderId): JsonResponse
    {
        try {
            $shippingOrder = ShippingOrder::findOrFail($shippingOrderId);

            // Get latest status from API if available
            if ($shippingOrder->api_order_id) {
                try {
                    // Use tracking info to get status if tracking number is available
                    if ($shippingOrder->tracking_number) {
                        $apiStatus = $this->messagingService->getTrackingInfo($shippingOrder->tracking_number);
                        
                        // Update local record
                        $shippingOrder->update([
                            'status' => $apiStatus['status'] ?? $shippingOrder->status,
                            'tracking_number' => $apiStatus['tracking_number'] ?? $shippingOrder->tracking_number,
                            'estimated_delivery' => isset($apiStatus['estimated_delivery']) ? 
                                Carbon::parse($apiStatus['estimated_delivery']) : 
                                $shippingOrder->estimated_delivery,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to get latest status from API', [
                        'shipping_order_id' => $shippingOrder->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $shippingOrder->id,
                    'order_id' => $shippingOrder->order_id,
                    'api_order_id' => $shippingOrder->api_order_id,
                    'tracking_number' => $shippingOrder->tracking_number,
                    'status' => $shippingOrder->status,
                    'status_label' => $shippingOrder->status_label,
                    'service_type' => $shippingOrder->service_type,
                    'shipping_cost' => $shippingOrder->shipping_cost,
                    'estimated_delivery' => $shippingOrder->estimated_delivery,
                    'created_at' => $shippingOrder->created_at,
                    'updated_at' => $shippingOrder->updated_at,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping order not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to get shipping order status via API', [
                'shipping_order_id' => $shippingOrderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get shipping order status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tracking information
     */
    public function getTrackingInfo(Request $request, $trackingNumber): JsonResponse
    {
        try {
            $trackingInfo = $this->messagingService->getTrackingInfo($trackingNumber);

            return response()->json([
                'success' => true,
                'data' => $trackingInfo,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get tracking info via API', [
                'tracking_number' => $trackingNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get tracking information',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Webhook endpoint for status updates from Messaging Shipping API
     */
    public function webhook(Request $request): JsonResponse
    {
        // Validate webhook signature if configured
        if (!$this->validateWebhookSignature($request)) {
            Log::warning('Invalid webhook signature', [
                'headers' => $request->headers->all(),
                'body' => $request->getContent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid signature',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'event_type' => 'required|string',
            'api_order_id' => 'required|string',
            'status' => 'required|string',
            'tracking_number' => 'nullable|string',
            'estimated_delivery' => 'nullable|date',
            'timestamp' => 'required|date',
        ]);

        if ($validator->fails()) {
            Log::warning('Invalid webhook payload', [
                'payload' => $request->all(),
                'errors' => $validator->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid payload',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $shippingOrder = ShippingOrder::where('api_order_id', $request->api_order_id)->first();

            if (!$shippingOrder) {
                Log::warning('Webhook received for unknown shipping order', [
                    'api_order_id' => $request->api_order_id,
                    'payload' => $request->all(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Shipping order not found',
                ], 404);
            }

            // Update shipping order with new status
            $shippingOrder->update([
                'status' => $request->status,
                'tracking_number' => $request->tracking_number ?? $shippingOrder->tracking_number,
                'estimated_delivery' => $request->estimated_delivery ? 
                    Carbon::parse($request->estimated_delivery) : 
                    $shippingOrder->estimated_delivery,
            ]);

            // Fire event for other systems to react to status change
            event(new \Polycenter\MessagingShipping\Events\ShippingStatusUpdated($shippingOrder, $request->all()));

            Log::info('Shipping order status updated via webhook', [
                'shipping_order_id' => $shippingOrder->id,
                'old_status' => $shippingOrder->getOriginal('status'),
                'new_status' => $request->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process webhook', [
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel shipping order
     */
    public function cancelShippingOrder(Request $request, $shippingOrderId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $shippingOrder = ShippingOrder::findOrFail($shippingOrderId);
            
            $success = $this->orderShippingService->cancelShippingOrder(
                $shippingOrder->order,
                $request->reason
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Shipping order cancelled successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel shipping order',
                ], 400);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping order not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to cancel shipping order via API', [
                'shipping_order_id' => $shippingOrderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel shipping order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate webhook signature
     */
    protected function validateWebhookSignature(Request $request): bool
    {
        $webhookSecret = config('messaging-shipping.webhook_secret');
        
        if (!$webhookSecret) {
            // If no secret is configured, skip validation
            return true;
        }

        $signature = $request->header('X-Messaging-Shipping-Signature');
        $payload = $request->getContent();
        
        if (!$signature) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);
        
        return hash_equals($expectedSignature, $signature);
    }
}
