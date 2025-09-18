<?php

namespace Polycenter\MessagingShipping\Carriers;

use Webkul\Shipping\Carriers\AbstractShipping;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Models\CartShippingRate;
use Polycenter\MessagingShipping\Services\MessagingShippingService;
use Illuminate\Support\Facades\Log;

class MessagingShippingCarrier extends AbstractShipping
{
    /**
     * Shipping method carrier code.
     *
     * @var string
     */
    protected $code = 'messaging_shipping';

    /**
     * MessagingShipping service instance.
     *
     * @var MessagingShippingService
     */
    protected $messagingService;

    public function __construct()
    {
        $this->messagingService = app(MessagingShippingService::class);
    }

    /**
     * Calculate shipping rates for MessagingShipping.
     *
     * @return array|false
     */
    public function calculate()
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $cart = Cart::getCart();
        
        if (! $cart || ! $cart->shipping_address) {
            return false;
        }

        try {
            // Get rates from MessagingShipping API
            $rates = $this->getShippingRates($cart);
            
            if (empty($rates)) {
                return false;
            }

            $cartShippingRates = [];

            foreach ($rates as $rate) {
                $cartShippingRate = new CartShippingRate;
                
                $cartShippingRate->carrier = $this->getCode();
                $cartShippingRate->carrier_title = $this->getConfigData('title') ?: 'MessagingShipping';
                $cartShippingRate->method = $this->getCode() . '_' . $rate['service_code'];
                $cartShippingRate->method_title = $rate['service'];
                $cartShippingRate->method_description = $rate['delivery_time'];
                $cartShippingRate->price = core()->convertPrice($rate['price']);
                $cartShippingRate->base_price = $rate['price'];

                $cartShippingRates[] = $cartShippingRate;
            }

            return $cartShippingRates;

        } catch (\Exception $e) {
            Log::error('[MessagingShipping Carrier] Failed to calculate rates', [
                'error' => $e->getMessage(),
                'cart_id' => $cart->id ?? null
            ]);

            return false;
        }
    }

    /**
     * Get shipping rates from MessagingShipping service.
     *
     * @param $cart
     * @return array
     */
    private function getShippingRates($cart): array
    {
        $shippingAddress = $cart->shipping_address;
        
        // Build shipping data from cart
        $shippingData = [
            'origin' => [
                'city_code' => $this->getConfigData('origin_city_code') ?: '11001',
                'postal_code' => $this->getConfigData('origin_postal_code') ?: '110111',
                'city' => $this->getConfigData('origin_city') ?: 'Bogotá',
                'state' => $this->getConfigData('origin_state') ?: 'Bogotá D.C.',
                'country' => $this->getConfigData('origin_country') ?: 'CO',
            ],
            'destination' => [
                'city_code' => $this->getCityCode($shippingAddress->city),
                'postal_code' => $shippingAddress->postcode,
                'city' => $shippingAddress->city,
                'state' => $shippingAddress->state,
                'country' => $shippingAddress->country,
            ],
            'packages' => $this->buildPackagesFromCart($cart),
        ];

        $result = $this->messagingService->getShippingRates($shippingData);
        
        if ($result['success']) {
            return $result['rates'] ?? [];
        }

        return [];
    }

    /**
     * Build packages array from cart items.
     *
     * @param $cart
     * @return array
     */
    private function buildPackagesFromCart($cart): array
    {
        $packages = [];
        $totalWeight = 0;
        $totalValue = 0;

        foreach ($cart->items as $item) {
            if ($item->getTypeInstance()->isStockable()) {
                $totalWeight += ($item->weight ?: 1) * $item->quantity;
                $totalValue += $item->price * $item->quantity;
            }
        }

        // If no weight is set, use default
        if ($totalWeight <= 0) {
            $totalWeight = 1;
        }

        // Create a single package for all items
        $packages[] = [
            'weight' => $totalWeight,
            'length' => $this->getConfigData('default_length') ?: 30,
            'width' => $this->getConfigData('default_width') ?: 20,
            'height' => $this->getConfigData('default_height') ?: 15,
            'declared_value' => $totalValue,
        ];

        return $packages;
    }

    /**
     * Get city code for shipping calculation.
     *
     * @param string $city
     * @return string
     */
    private function getCityCode($city): string
    {
        // Map of common Colombian cities to their codes
        $cityMap = [
            'Bogotá' => '11001',
            'Medellín' => '050001',
            'Cali' => '76001',
            'Barranquilla' => '080001',
            'Cartagena' => '130001',
            'Bucaramanga' => '680001',
            'Pereira' => '660001',
            'Santa Marta' => '470001',
        ];

        // Try to find exact match
        if (isset($cityMap[$city])) {
            return $cityMap[$city];
        }

        // Try partial match
        foreach ($cityMap as $cityName => $code) {
            if (stripos($city, $cityName) !== false) {
                return $code;
            }
        }

        // Default fallback
        return '11001';
    }

    /**
     * Check if MessagingShipping is available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        $isActive = $this->getConfigData('active');
        
        if (!$isActive) {
            return false;
        }

        // Check if we have API credentials configured
        $apiKey = config('messaging-shipping.api_key');
        $apiSecret = config('messaging-shipping.api_secret');
        
        return !empty($apiKey) && !empty($apiSecret);
    }

    /**
     * Get tracking URL for a shipment
     */
    public function getTrackingUrl(string $trackingNumber): ?string
    {
        if (empty($trackingNumber)) {
            return null;
        }

        $baseUrl = config('messaging-shipping.tracking_url', 'https://track.envia.com');
        return $baseUrl . '/track/' . $trackingNumber;
    }
}
