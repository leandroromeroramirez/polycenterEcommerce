<?php

namespace Polycenter\MessagingShipping\Services;

use Polycenter\MessagingShipping\Services\MessagingShippingService;
use Polycenter\MessagingShipping\Models\ShippingOrder;
use Webkul\Sales\Models\Order;

class OrderShippingService
{
    public function __construct(
        protected MessagingShippingService $messagingService
    ) {}

    /**
     * Create shipping order when payment is completed
     */
    public function createShippingOrder(Order $order): ?ShippingOrder
    {
        try {
            // Prepare shipping order data
            $shippingData = $this->prepareShippingOrderData($order);
            
            // Create shipping order via API
            $apiResponse = $this->messagingService->createShippingOrder($shippingData);
            
            // Store shipping order locally
            $shippingOrder = $this->storeShippingOrder($order, $apiResponse);
            
            logger()->info('[Messaging Shipping] Shipping order created successfully', [
                'order_id' => $order->id,
                'shipping_order_id' => $shippingOrder->id,
                'api_order_id' => $apiResponse['order_id'] ?? null,
            ]);
            
            return $shippingOrder;
        } catch (\Exception $e) {
            logger()->error('[Messaging Shipping] Failed to create shipping order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            
            return null;
        }
    }

    /**
     * Prepare shipping order data for API
     */
    protected function prepareShippingOrderData(Order $order): array
    {
        $shippingAddress = $order->shipping_address;
        $billingAddress = $order->billing_address;
        
        return [
            'reference_number' => $order->increment_id,
            'service_type' => $this->extractServiceType($order),
            'origin' => config('messaging-shipping.default_origin'),
            'destination' => [
                'name' => $shippingAddress->name,
                'company' => $shippingAddress->company_name ?? '',
                'phone' => $shippingAddress->phone,
                'email' => $order->customer_email,
                'country' => $shippingAddress->country,
                'state' => $shippingAddress->state,
                'city' => $shippingAddress->city,
                'postal_code' => $shippingAddress->postcode,
                'address' => $shippingAddress->address,
            ],
            'sender' => [
                'name' => config('app.name'),
                'company' => config('app.name'),
                'phone' => config('messaging-shipping.default_origin.phone', ''),
                'email' => config('mail.from.address'),
                'country' => config('messaging-shipping.default_origin.country'),
                'state' => config('messaging-shipping.default_origin.state'),
                'city' => config('messaging-shipping.default_origin.city'),
                'postal_code' => config('messaging-shipping.default_origin.postal_code'),
                'address' => config('messaging-shipping.default_origin.address'),
            ],
            'packages' => $this->preparePackagesData($order),
            'declared_value' => $order->grand_total,
            'currency' => $order->order_currency_code,
            'payment_method' => 'prepaid', // Since payment is already processed
            'insurance' => [
                'enabled' => false,
                'value' => 0,
            ],
            'special_instructions' => $order->notes ?? '',
            'webhook_url' => route('api.messaging-shipping.webhook'),
        ];
    }

    /**
     * Extract service type from order shipping method
     */
    protected function extractServiceType(Order $order): string
    {
        $shippingMethod = $order->shipping_method ?? '';
        
        // Extract service type from shipping method
        // Format: messaging-shipping_express, messaging-shipping_standard, etc.
        if (str_contains($shippingMethod, 'messaging-shipping_')) {
            return str_replace('messaging-shipping_', '', $shippingMethod);
        }
        
        return 'standard'; // Default service type
    }

    /**
     * Prepare packages data from order items
     */
    protected function preparePackagesData(Order $order): array
    {
        $packages = [];
        $totalWeight = 0;
        $totalVolume = 0;
        $items = [];

        foreach ($order->items as $orderItem) {
            $product = $orderItem->product;
            
            $weight = ($product->weight ?? 0.5) * $orderItem->qty_ordered;
            $length = $product->length ?? 10;
            $width = $product->width ?? 10;
            $height = $product->height ?? 10;
            
            $totalWeight += $weight;
            $totalVolume += ($length * $width * $height) * $orderItem->qty_ordered;
            
            $items[] = [
                'sku' => $orderItem->sku,
                'name' => $orderItem->name,
                'quantity' => $orderItem->qty_ordered,
                'value' => $orderItem->price,
                'weight' => $weight,
                'description' => $orderItem->name,
            ];
        }

        // Create single package for simplicity
        $packages[] = [
            'weight' => $totalWeight,
            'dimensions' => [
                'length' => min(ceil(pow($totalVolume, 1/3) * 1.2), 120),
                'width' => min(ceil(pow($totalVolume, 1/3) * 1.0), 80),
                'height' => min(ceil(pow($totalVolume, 1/3) * 0.8), 80),
            ],
            'items' => $items,
            'description' => 'Order #' . $order->increment_id . ' from ' . config('app.name'),
        ];

        return $packages;
    }

    /**
     * Store shipping order locally
     */
    protected function storeShippingOrder(Order $order, array $apiResponse): ShippingOrder
    {
        return ShippingOrder::create([
            'order_id' => $order->id,
            'api_order_id' => $apiResponse['order_id'] ?? null,
            'tracking_number' => $apiResponse['tracking_number'] ?? null,
            'status' => $apiResponse['status'] ?? 'pending',
            'service_type' => $this->extractServiceType($order),
            'estimated_delivery' => $apiResponse['estimated_delivery'] ?? null,
            'shipping_cost' => $apiResponse['shipping_cost'] ?? $order->shipping_amount,
            'api_response' => $apiResponse,
        ]);
    }

    /**
     * Update shipping order status
     */
    public function updateShippingOrderStatus(string $apiOrderId, string $status, array $additionalData = []): bool
    {
        try {
            $shippingOrder = ShippingOrder::where('api_order_id', $apiOrderId)->first();
            
            if (!$shippingOrder) {
                logger()->warning('[Messaging Shipping] Shipping order not found for update', [
                    'api_order_id' => $apiOrderId,
                    'status' => $status,
                ]);
                return false;
            }

            $updateData = [
                'status' => $status,
                'updated_at' => now(),
            ];

            // Update tracking number if provided
            if (isset($additionalData['tracking_number'])) {
                $updateData['tracking_number'] = $additionalData['tracking_number'];
            }

            // Update estimated delivery if provided
            if (isset($additionalData['estimated_delivery'])) {
                $updateData['estimated_delivery'] = $additionalData['estimated_delivery'];
            }

            // Store webhook data
            if (!empty($additionalData)) {
                $updateData['webhook_data'] = array_merge(
                    $shippingOrder->webhook_data ?? [],
                    [$additionalData]
                );
            }

            $shippingOrder->update($updateData);

            // Update Bagisto shipment status if needed
            $this->updateBagistoShipmentStatus($shippingOrder, $status);

            logger()->info('[Messaging Shipping] Shipping order status updated', [
                'shipping_order_id' => $shippingOrder->id,
                'api_order_id' => $apiOrderId,
                'old_status' => $shippingOrder->getOriginal('status'),
                'new_status' => $status,
            ]);

            return true;
        } catch (\Exception $e) {
            logger()->error('[Messaging Shipping] Failed to update shipping order status', [
                'api_order_id' => $apiOrderId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Update Bagisto shipment status based on shipping order status
     */
    protected function updateBagistoShipmentStatus(ShippingOrder $shippingOrder, string $status): void
    {
        $order = $shippingOrder->order;
        
        if (!$order) {
            return;
        }

        // Map shipping statuses to Bagisto shipment actions
        switch ($status) {
            case 'picked_up':
            case 'in_transit':
                // Create shipment if not exists
                if (!$order->shipments()->exists()) {
                    $this->createBagistoShipment($order, $shippingOrder);
                }
                break;
                
            case 'delivered':
                // Mark shipment as delivered if exists
                if ($shipment = $order->shipments()->first()) {
                    // Bagisto doesn't have a built-in "delivered" status
                    // This could be extended with custom shipment statuses
                }
                break;
        }
    }

    /**
     * Create Bagisto shipment
     */
    protected function createBagistoShipment(Order $order, ShippingOrder $shippingOrder): void
    {
        try {
            // Prepare shipment data
            $shipmentData = [
                'order_id' => $order->id,
                'carrier_title' => 'Messaging Shipping',
                'track_number' => $shippingOrder->tracking_number,
                'inventory_source_id' => 1, // Default inventory source
                'shipment' => [
                    'items' => [],
                ],
            ];

            // Add order items to shipment
            foreach ($order->items as $orderItem) {
                $shipmentData['shipment']['items'][$orderItem->id] = $orderItem->qty_ordered;
            }

            // Create shipment using Bagisto's shipment repository
            app('Webkul\Sales\Repositories\ShipmentRepository')->create($shipmentData);

            logger()->info('[Messaging Shipping] Bagisto shipment created', [
                'order_id' => $order->id,
                'shipping_order_id' => $shippingOrder->id,
            ]);
        } catch (\Exception $e) {
            logger()->error('[Messaging Shipping] Failed to create Bagisto shipment', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get shipping order by Bagisto order
     */
    public function getShippingOrderByOrder(Order $order): ?ShippingOrder
    {
        return ShippingOrder::where('order_id', $order->id)->first();
    }

    /**
     * Cancel shipping order
     */
    public function cancelShippingOrder(Order $order, string $reason = ''): bool
    {
        try {
            $shippingOrder = $this->getShippingOrderByOrder($order);
            
            if (!$shippingOrder || !$shippingOrder->api_order_id) {
                return false;
            }

            // Cancel via API
            $this->messagingService->cancelShippingOrder($shippingOrder->api_order_id, $reason);

            // Update local status
            $shippingOrder->update([
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
            ]);

            logger()->info('[Messaging Shipping] Shipping order cancelled', [
                'order_id' => $order->id,
                'shipping_order_id' => $shippingOrder->id,
                'reason' => $reason,
            ]);

            return true;
        } catch (\Exception $e) {
            logger()->error('[Messaging Shipping] Failed to cancel shipping order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
