<?php

namespace Polycenter\MessagingShipping\Listeners;

use Polycenter\MessagingShipping\Services\OrderShippingService;
use Webkul\Sales\Models\Order;
use Illuminate\Support\Facades\Log;

class OrderPlacedListener
{
    public function __construct(
        protected OrderShippingService $orderShippingService
    ) {}

    /**
     * Handle the order placed event
     */
    public function handle($event): void
    {
        $order = $event->order ?? $event;
        
        if (!$order instanceof Order) {
            return;
        }

        // Only process orders that use messaging shipping
        if (!$this->shouldProcessOrder($order)) {
            return;
        }

        try {
            Log::info('[Messaging Shipping] Processing order for shipping', [
                'order_id' => $order->id,
                'order_increment_id' => $order->increment_id,
                'shipping_method' => $order->shipping_method,
            ]);

            // Create shipping order
            $shippingOrder = $this->orderShippingService->createShippingOrder($order);

            if ($shippingOrder) {
                Log::info('[Messaging Shipping] Shipping order created successfully', [
                    'order_id' => $order->id,
                    'shipping_order_id' => $shippingOrder->id,
                    'api_order_id' => $shippingOrder->api_order_id,
                ]);
            } else {
                Log::warning('[Messaging Shipping] Failed to create shipping order', [
                    'order_id' => $order->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[Messaging Shipping] Exception while processing order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check if order should be processed by messaging shipping
     */
    protected function shouldProcessOrder(Order $order): bool
    {
        // Check if order uses messaging shipping
        $shippingMethod = $order->shipping_method ?? '';
        
        if (!str_contains($shippingMethod, 'messaging-shipping')) {
            return false;
        }

        // Check if order is paid (or has the correct status)
        if (!in_array($order->status, ['processing', 'completed'])) {
            Log::info('[Messaging Shipping] Order not in correct status for shipping', [
                'order_id' => $order->id,
                'status' => $order->status,
            ]);
            return false;
        }

        // Check if shipping order already exists
        $existingShippingOrder = $this->orderShippingService->getShippingOrderByOrder($order);
        if ($existingShippingOrder) {
            Log::info('[Messaging Shipping] Shipping order already exists', [
                'order_id' => $order->id,
                'existing_shipping_order_id' => $existingShippingOrder->id,
            ]);
            return false;
        }

        // Check if order has required shipping address
        if (!$order->shipping_address) {
            Log::warning('[Messaging Shipping] Order missing shipping address', [
                'order_id' => $order->id,
            ]);
            return false;
        }

        return true;
    }
}
