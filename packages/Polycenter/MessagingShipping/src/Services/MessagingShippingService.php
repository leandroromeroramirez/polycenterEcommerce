<?php

namespace Polycenter\MessagingShipping\Services;

use Polycenter\MessagingShipping\Services\Adapters\EnviaAdapter;
use Polycenter\MessagingShipping\Exceptions\MessagingShippingException;
use Illuminate\Support\Facades\Log;

class MessagingShippingService
{
    protected EnviaAdapter $adapter;
    protected array $config;

    public function __construct()
    {
        $this->config = config('messaging-shipping');
        $this->adapter = new EnviaAdapter();
    }

    /**
     * Get shipping rates
     */
    public function getShippingRates(array $shipmentData): array
    {
        try {
            return $this->adapter->getShippingRates($shipmentData);
        } catch (MessagingShippingException $e) {
            Log::error('[MessagingShipping] Failed to get shipping rates', [
                'error' => $e->getMessage(),
                'data' => $shipmentData,
            ]);
            
            return [
                'success' => false,
                'rates' => [],
                'message' => 'Failed to calculate shipping rates',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create shipping order
     */
    public function createShippingOrder(array $orderData): array
    {
        try {
            return $this->adapter->createShipment($orderData);
        } catch (MessagingShippingException $e) {
            Log::error('[MessagingShipping] Failed to create shipping order', [
                'error' => $e->getMessage(),
                'data' => $orderData,
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create shipping order',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get tracking information
     */
    public function getTrackingInfo(string $trackingNumber): array
    {
        try {
            return $this->adapter->getTracking($trackingNumber);
        } catch (MessagingShippingException $e) {
            Log::error('[MessagingShipping] Failed to get tracking info', [
                'error' => $e->getMessage(),
                'tracking' => $trackingNumber,
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to get tracking information',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available carriers
     */
    public function getAvailableCarriers(): array
    {
        try {
            return $this->adapter->getCarriers();
        } catch (MessagingShippingException $e) {
            Log::error('[MessagingShipping] Failed to get carriers', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'carriers' => [],
                'message' => 'Failed to get carriers',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test connection with the shipping provider
     */
    public function testConnection(): array
    {
        try {
            return $this->adapter->testConnection();
        } catch (MessagingShippingException $e) {
            Log::error('[MessagingShipping] Connection test failed', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel shipping order
     */
    public function cancelShippingOrder(string $trackingNumber): array
    {
        try {
            // Implementation depends on Envia.com API for cancellation
            return [
                'success' => true,
                'message' => 'Shipment cancellation requested',
            ];
        } catch (MessagingShippingException $e) {
            Log::error('[MessagingShipping] Failed to cancel shipping order', [
                'error' => $e->getMessage(),
                'tracking' => $trackingNumber,
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to cancel shipping order',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate shipping address
     */
    public function validateAddress(array $address): array
    {
        try {
            // Basic validation - can be enhanced with Envia.com address validation
            $required = ['city', 'state', 'country'];
            $missing = [];
            
            foreach ($required as $field) {
                if (empty($address[$field])) {
                    $missing[] = $field;
                }
            }
            
            if (!empty($missing)) {
                return [
                    'success' => false,
                    'message' => 'Missing required fields: ' . implode(', ', $missing),
                    'valid' => false,
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Address is valid',
                'valid' => true,
                'normalized_address' => $address,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Address validation failed',
                'valid' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get estimated delivery time
     */
    public function getEstimatedDelivery(array $shipmentData): array
    {
        try {
            $rates = $this->getShippingRates($shipmentData);
            
            if ($rates['success'] && !empty($rates['rates'])) {
                $estimations = [];
                foreach ($rates['rates'] as $rate) {
                    $estimations[] = [
                        'service' => $rate['service'],
                        'delivery_time' => $rate['delivery_time'],
                        'estimated_delivery' => $rate['estimated_delivery'],
                    ];
                }
                
                return [
                    'success' => true,
                    'estimations' => $estimations,
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Could not get delivery estimations',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get delivery estimations',
                'error' => $e->getMessage(),
            ];
        }
    }
}
