<?php

namespace Polycenter\MessagingShipping\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Polycenter\MessagingShipping\Services\MessagingShippingService;
use Polycenter\MessagingShipping\Exceptions\MessagingShippingException;
use Webkul\Checkout\Models\Cart;
use Webkul\Customer\Models\CustomerAddress;

class ShippingCalculatorService
{
    public function __construct(
        protected MessagingShippingService $messagingService
    ) {}

    /**
     * Calculate shipping rates from raw data (for API usage)
     */
    public function calculateRatesFromData(array $shippingData): array
    {
        try {
            // Test connection first
            $connectionTest = $this->messagingService->testConnection();
            
            if (!$connectionTest['success']) {
                throw new MessagingShippingException('Failed to connect to Messaging Shipping API: ' . ($connectionTest['message'] ?? 'Unknown error'));
            }

            $requestData = [
                'origin' => $shippingData['origin'],
                'destination' => $shippingData['destination'],
                'packages' => $shippingData['packages'],
                'declared_value' => $shippingData['declared_value'] ?? null,
            ];

            $response = $this->messagingService->getShippingRates($requestData);

            if (!$response || !isset($response['rates'])) {
                throw new MessagingShippingException('Invalid response from shipping API');
            }

            return $this->processRatesResponse($response['rates']);
        } catch (\Exception $e) {
            Log::error('Failed to calculate shipping rates from data', [
                'shipping_data' => $shippingData,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process rates response from API
     */
    protected function processRatesResponse(array $rates): array
    {
        $processedRates = [];
        $serviceTypes = config('messaging-shipping.service_types');

        foreach ($rates as $rate) {
            $serviceType = $rate['service_type'] ?? 'standard';
            $serviceConfig = $serviceTypes[$serviceType] ?? $serviceTypes['standard'];
            
            $processedRates[] = [
                'service_type' => $serviceType,
                'service_name' => $serviceConfig['name'],
                'price' => $rate['price'] ?? 0,
                'estimated_delivery' => $rate['estimated_delivery'] ?? null,
                'estimated_days' => $rate['estimated_days'] ?? null,
                'tracking_available' => $rate['tracking_available'] ?? true,
                'insurance_available' => $rate['insurance_available'] ?? false,
            ];
        }

        return $processedRates;
    }

    /**
     * Calculate shipping rates for cart
     */
    public function calculateShippingRates(Cart $cart, CustomerAddress $shippingAddress): Collection
    {
        $shipmentData = $this->prepareShipmentData($cart, $shippingAddress);
        
        try {
            $quote = $this->messagingService->getShippingRates($shipmentData);
            return $this->parseShippingRates($quote);
        } catch (\Exception $e) {
            logger()->error('[Messaging Shipping] Failed to calculate shipping rates', [
                'error' => $e->getMessage(),
                'cart_id' => $cart->id,
                'shipping_address' => $shippingAddress->toArray(),
            ]);
            
            return collect();
        }
    }

    /**
     * Prepare shipment data for API
     */
    protected function prepareShipmentData(Cart $cart, CustomerAddress $shippingAddress): array
    {
        $config = config('messaging-shipping', []);
        
        $packages = $this->calculatePackages($cart);
        
        // Get origin configuration from Bagisto core config or fallback to defaults
        $origin = [
            'city_code' => core()->getConfigData('sales.carriers.messaging_shipping.origin_city_code') ?? '11001',
            'postal_code' => core()->getConfigData('sales.carriers.messaging_shipping.origin_postal_code') ?? '110111',
            'city' => core()->getConfigData('sales.carriers.messaging_shipping.origin_city') ?? 'Bogotá',
            'state' => core()->getConfigData('sales.carriers.messaging_shipping.origin_state') ?? 'Bogotá D.C.',
            'country' => core()->getConfigData('sales.carriers.messaging_shipping.origin_country') ?? 'CO',
        ];
        
        return [
            'origin' => $origin,
            'destination' => [
                'country' => $shippingAddress->country,
                'state' => $shippingAddress->state,
                'city' => $shippingAddress->city,
                'postal_code' => $shippingAddress->postcode,
                'address' => $shippingAddress->address,
            ],
            'packages' => $packages,
            'service_types' => array_keys($config['service_types']),
            'declared_value' => $cart->grand_total,
            'currency' => $cart->global_currency_code,
        ];
    }

    /**
     * Calculate packages from cart items
     */
    protected function calculatePackages(Cart $cart): array
    {
        $totalWeight = 0;
        $totalVolume = 0;
        $packages = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            
            // Get product dimensions and weight with better defaults
            $weight = $product->weight ?? 
                     core()->getConfigData('sales.carriers.messaging_shipping.default_weight') ?? 
                     0.5; // Default 0.5kg if not specified
            
            $length = $product->length ?? 
                     core()->getConfigData('sales.carriers.messaging_shipping.default_length') ?? 
                     30;   // Default dimensions in cm
            
            $width = $product->width ?? 
                    core()->getConfigData('sales.carriers.messaging_shipping.default_width') ?? 
                    20;
            
            $height = $product->height ?? 
                     core()->getConfigData('sales.carriers.messaging_shipping.default_height') ?? 
                     15;
            
            $itemWeight = $weight * $item->quantity;
            $itemVolume = ($length * $width * $height) * $item->quantity;
            
            $totalWeight += $itemWeight;
            $totalVolume += $itemVolume;
        }

        // For simplicity, create one package with all items
        // In a real scenario, you might want to split into multiple packages
        $packages[] = [
            'weight' => $totalWeight,
            'length' => $this->calculateDimensionFromVolume($totalVolume, 'length'),
            'width' => $this->calculateDimensionFromVolume($totalVolume, 'width'),
            'height' => $this->calculateDimensionFromVolume($totalVolume, 'height'),
            'declared_value' => $cart->grand_total ?? 0,
        ];

        return $packages;
    }

    /**
     * Calculate estimated dimension from volume
     */
    protected function calculateDimensionFromVolume(float $volume, string $dimension): float
    {
        // Simple cube root approximation for package dimensions
        $cubeDimension = pow($volume, 1/3);
        
        // Apply some variation based on dimension type
        switch ($dimension) {
            case 'length':
                return max(ceil($cubeDimension * 1.2), 10);
            case 'width':
                return max(ceil($cubeDimension * 1.0), 10);
            case 'height':
                return max(ceil($cubeDimension * 0.8), 10);
            default:
                return max(ceil($cubeDimension), 10);
        }
    }

    /**
     * Parse API response into shipping rates
     */
    protected function parseShippingRates(array $apiResponse): Collection
    {
        $rates = collect();
        
        // Check if response has rates array
        if (!isset($apiResponse['rates']) || !is_array($apiResponse['rates'])) {
            return $rates;
        }

        foreach ($apiResponse['rates'] as $rate) {
            // Map the service code to a more readable format
            $serviceCode = $rate['service_code'] ?? 'standard';
            $serviceName = $rate['service'] ?? 'Envío Estándar';
            
            $rates->push([
                'carrier' => 'messaging-shipping',
                'carrier_title' => 'MessagingShipping',
                'method' => $serviceCode,
                'method_title' => $serviceName,
                'method_description' => $rate['delivery_time'] ?? 'Servicio de envío',
                'price' => $rate['price'] ?? 0,
                'base_price' => $rate['price'] ?? 0,
                'formatted_price' => core()->currency($rate['price'] ?? 0),
                'estimated_delivery' => $rate['estimated_delivery'] ?? null,
                'service_data' => [
                    'rate_id' => $rate['id'] ?? null,
                    'service_type' => $serviceCode,
                    'delivery_time' => $rate['delivery_time'] ?? null,
                    'carrier' => $rate['carrier'] ?? 'MessagingShipping',
                    'currency' => $rate['currency'] ?? 'COP',
                ],
            ]);
        }

        return $rates->sortBy('price');
    }

    /**
     * Validate shipping address
     */
    public function validateShippingAddress(CustomerAddress $address): array
    {
        try {
            $addressData = [
                'country' => $address->country,
                'state' => $address->state,
                'city' => $address->city,
                'postal_code' => $address->postcode,
                'address' => $address->address,
            ];

            return $this->messagingService->validateAddress($addressData);
        } catch (\Exception $e) {
            logger()->error('[Messaging Shipping] Address validation failed', [
                'error' => $e->getMessage(),
                'address' => $address->toArray(),
            ]);

            return [
                'valid' => false,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Get estimated delivery time
     */
    public function getEstimatedDelivery(string $serviceType, CustomerAddress $shippingAddress): ?string
    {
        $serviceTypes = config('messaging-shipping.service_types');
        
        if (!isset($serviceTypes[$serviceType])) {
            return null;
        }

        // This could be enhanced to make an API call for more precise delivery estimates
        $baseDays = match($serviceType) {
            'overnight' => 1,
            'express' => 2,
            'standard' => 5,
            default => 5,
        };

        return now()->addDays($baseDays)->format('Y-m-d');
    }
}
