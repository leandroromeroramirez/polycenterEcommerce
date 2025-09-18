<?php

namespace Polycenter\MessagingShipping\Services\Adapters;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Polycenter\MessagingShipping\Exceptions\MessagingShippingException;

class EnviaAdapter
{
    protected Client $client;
    protected array $config;
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->config = config('messaging-shipping');
        $this->client = new Client([
            'base_uri' => $this->config['api_url'],
            'timeout' => $this->config['timeout'],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

        /**
     * Authenticate with Envia.com API
     */
    private function authenticate(): string
    {
        $cacheKey = 'envia_access_token';
        
        if ($this->accessToken && Cache::has($cacheKey)) {
            $this->accessToken = Cache::get($cacheKey);
            return $this->accessToken;
        }

        try {
            // Envia.com specific authentication endpoint
            $endpoint = '/ship/token/';
            
            // Try different authentication formats
            $authMethods = [
                // Method 1: Standard OAuth format
                [
                    'json' => [
                        'client_id' => $this->config['api_key'],
                        'client_secret' => $this->config['api_secret'],
                        'grant_type' => 'client_credentials',
                    ],
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ],
                // Method 2: Basic Auth
                [
                    'auth' => [$this->config['api_key'], $this->config['api_secret']],
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ],
                // Method 3: API Key format
                [
                    'json' => [
                        'api_key' => $this->config['api_key'],
                        'api_secret' => $this->config['api_secret'],
                    ],
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ],
                // Method 4: Form data
                [
                    'form_params' => [
                        'client_id' => $this->config['api_key'],
                        'client_secret' => $this->config['api_secret'],
                        'grant_type' => 'client_credentials',
                    ],
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ],
            ];

            foreach ($authMethods as $index => $options) {
                try {
                    Log::info('[Envia] Attempting authentication method', [
                        'method' => $index + 1,
                        'endpoint' => $endpoint
                    ]);

                    $response = $this->client->post($endpoint, $options);
                    $data = json_decode($response->getBody()->getContents(), true);
                    
                    if (isset($data['access_token'])) {
                        $this->accessToken = $data['access_token'];
                        
                        // Cache token for 50 minutes (assuming 1 hour expiry)
                        Cache::put($cacheKey, $this->accessToken, now()->addMinutes(50));
                        
                        Log::info('[Envia] Authentication successful', [
                            'method' => $index + 1,
                            'expires_in' => $data['expires_in'] ?? 'unknown'
                        ]);
                        
                        return $this->accessToken;
                    } elseif (isset($data['token'])) {
                        // Some APIs use 'token' instead of 'access_token'
                        $this->accessToken = $data['token'];
                        Cache::put($cacheKey, $this->accessToken, now()->addMinutes(50));
                        
                        Log::info('[Envia] Authentication successful with token field', [
                            'method' => $index + 1
                        ]);
                        
                        return $this->accessToken;
                    }
                } catch (RequestException $e) {
                    $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
                    $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';
                    
                    Log::warning('[Envia] Authentication method failed', [
                        'method' => $index + 1,
                        'status' => $statusCode,
                        'error' => $e->getMessage(),
                        'response' => $responseBody
                    ]);
                    
                    // If we get 401, continue trying other methods
                    // If we get something else, it might be a server issue
                    if ($statusCode !== 401) {
                        throw $e;
                    }
                    continue;
                }
            }

            throw new MessagingShippingException('All authentication methods failed. Please verify your Envia.com credentials.');

        } catch (RequestException $e) {
            $errorMessage = 'Envia.com authentication failed: ' . $e->getMessage();
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $errorMessage .= ' Response: ' . $errorBody;
            }
            
            Log::error('[Envia] Authentication error', [
                'error' => $errorMessage,
                'api_key' => substr($this->config['api_key'], 0, 8) . '...' // Log only first 8 chars for security
            ]);
            
            throw new MessagingShippingException($errorMessage);
        }
    }

        /**
     * Get shipping rates from Envia.com API
     */
    public function getShippingRates(array $shipmentData): array
    {
        // For sandbox/development mode, return mock data
        if ($this->config['sandbox'] ?? true) {
            return $this->getMockShippingRates($shipmentData);
        }

        try {
            $token = $this->authenticate();
            
            $response = $this->client->post('/ship/rate/', [
                'json' => [
                    'shipment' => $this->formatShipmentForEnvia($shipmentData),
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return [
                'success' => true,
                'rates' => $this->formatEnviaRates($data),
            ];
        } catch (RequestException $e) {
            Log::error('[Envia] Failed to get shipping rates', [
                'error' => $e->getMessage(),
                'shipment' => $shipmentData
            ]);
            
            throw new MessagingShippingException('Failed to get shipping rates: ' . $e->getMessage());
        }
    }

    /**
     * Create shipment with Envia.com
     */
    public function createShipment(array $shipmentData): array
    {
        $token = $this->authenticate();

        $enviaData = $this->transformToEnviaShipment($shipmentData);

        try {
            $response = $this->client->post('/ship/generate/', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
                'json' => $enviaData,
            ]);

            $result = json_decode($response->getBody(), true);
            
            return $this->transformFromEnviaShipment($result);
        } catch (GuzzleException $e) {
            Log::error('Envia.com shipment creation failed', [
                'error' => $e->getMessage(),
                'data' => $enviaData,
            ]);
            throw new MessagingShippingException('Failed to create shipment with Envia.com: ' . $e->getMessage());
        }
    }

    /**
     * Get tracking information from Envia.com
     */
    public function getTracking(string $trackingNumber): array
    {
        $token = $this->authenticate();

        try {
            $response = $this->client->get("/ship/track/{$trackingNumber}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            $result = json_decode($response->getBody(), true);
            
            return $this->transformFromEnviaTracking($result);
        } catch (GuzzleException $e) {
            Log::error('Envia.com tracking request failed', [
                'error' => $e->getMessage(),
                'tracking' => $trackingNumber,
            ]);
            throw new MessagingShippingException('Failed to get tracking from Envia.com: ' . $e->getMessage());
        }
    }

    /**
     * Get available carriers from Envia.com
     */
    public function getCarriers(): array
    {
        // For sandbox/development mode, return mock data
        if ($this->config['sandbox'] ?? true) {
            return [
                'success' => true,
                'carriers' => [
                    [
                        'name' => 'Envia.com Standard',
                        'service' => 'standard',
                        'active' => true
                    ],
                    [
                        'name' => 'Envia.com Express',
                        'service' => 'express',
                        'active' => true
                    ]
                ],
                'sandbox' => true
            ];
        }

        $token = $this->authenticate();

        try {
            $response = $this->client->get('/catalogs/couriers', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            $result = json_decode($response->getBody(), true);
            
            return $this->transformFromEnviaCarriers($result);
        } catch (GuzzleException $e) {
            Log::error('[Envia] Failed to get carriers', [
                'error' => $e->getMessage()
            ]);
            
            throw new MessagingShippingException('Failed to get carriers: ' . $e->getMessage());
        }
    }

    /**
     * Transform our format to Envia.com rate request format
     */
    protected function transformToEnviaFormat(array $data): array
    {
        $origin = $data['origin'] ?? [];
        $destination = $data['destination'] ?? [];
        $packages = $data['packages'] ?? [];

        return [
            'origin' => [
                'name' => $origin['name'] ?? config('messaging-shipping.default_origin.name', 'Mi Tienda'),
                'company' => $origin['company'] ?? config('app.name'),
                'email' => $origin['email'] ?? config('mail.from.address'),
                'phone' => $origin['phone'] ?? '',
                'street' => $origin['address'] ?? config('messaging-shipping.default_origin.address'),
                'number' => $origin['number'] ?? '',
                'district' => $origin['district'] ?? '',
                'city' => $origin['city'] ?? config('messaging-shipping.default_origin.city'),
                'state' => $origin['state'] ?? config('messaging-shipping.default_origin.state'),
                'country' => $origin['country'] ?? config('messaging-shipping.default_origin.country'),
                'postalCode' => $origin['postal_code'] ?? config('messaging-shipping.default_origin.postal_code'),
            ],
            'destination' => [
                'name' => $destination['name'] ?? 'Cliente',
                'company' => $destination['company'] ?? '',
                'email' => $destination['email'] ?? '',
                'phone' => $destination['phone'] ?? '',
                'street' => $destination['address'] ?? '',
                'number' => $destination['number'] ?? '',
                'district' => $destination['district'] ?? '',
                'city' => $destination['city'] ?? '',
                'state' => $destination['state'] ?? '',
                'country' => $destination['country'] ?? 'CO',
                'postalCode' => $destination['postal_code'] ?? '',
            ],
            'packages' => array_map(function ($package) {
                return [
                    'content' => $package['content'] ?? 'Mercancía general',
                    'amount' => 1,
                    'type' => 'box',
                    'weight' => $package['weight'] ?? 1,
                    'insurance' => $package['declared_value'] ?? 0,
                    'declaredValue' => $package['declared_value'] ?? 0,
                    'weightUnit' => 'KG',
                    'lengthUnit' => 'CM',
                    'dimensions' => [
                        'length' => $package['length'] ?? 10,
                        'width' => $package['width'] ?? 10,
                        'height' => $package['height'] ?? 10,
                    ],
                ];
            }, $packages),
            'shipment' => [
                'carrier' => 'coordinadora', // Default Colombian carrier
                'service' => 'standard',
                'cashOnDelivery' => false,
            ],
        ];
    }

    /**
     * Transform Envia.com rate response to our format
     */
    protected function transformFromEnviaRates(array $data): array
    {
        $rates = [];

        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $rate) {
                $rates[] = [
                    'carrier' => $rate['carrier']['name'] ?? 'Unknown',
                    'carrier_code' => $rate['carrier']['code'] ?? '',
                    'service' => $rate['service']['name'] ?? 'Standard',
                    'service_code' => $rate['service']['code'] ?? 'standard',
                    'price' => $rate['totalPrice'] ?? 0,
                    'currency' => $rate['currency'] ?? 'COP',
                    'delivery_time' => $rate['deliveryTime'] ?? '3-5 días',
                    'estimated_delivery' => $rate['estimatedDelivery'] ?? null,
                    'description' => $rate['description'] ?? '',
                ];
            }
        }

        return [
            'success' => !empty($rates),
            'rates' => $rates,
            'message' => empty($rates) ? 'No rates available' : 'Rates retrieved successfully',
        ];
    }

    /**
     * Transform our format to Envia.com shipment format
     */
    protected function transformToEnviaShipment(array $data): array
    {
        // Similar to rate format but with selected service
        $enviaData = $this->transformToEnviaFormat($data);
        
        // Add shipment-specific data
        $enviaData['shipment']['carrier'] = $data['carrier'] ?? 'coordinadora';
        $enviaData['shipment']['service'] = $data['service'] ?? 'standard';
        
        return $enviaData;
    }

    /**
     * Transform Envia.com shipment response to our format
     */
    protected function transformFromEnviaShipment(array $data): array
    {
        return [
            'success' => isset($data['trackingNumber']),
            'tracking_number' => $data['trackingNumber'] ?? null,
            'shipment_id' => $data['id'] ?? null,
            'carrier' => $data['carrier']['name'] ?? '',
            'service' => $data['service']['name'] ?? '',
            'cost' => $data['cost'] ?? 0,
            'currency' => $data['currency'] ?? 'COP',
            'estimated_delivery' => $data['estimatedDelivery'] ?? null,
            'label_url' => $data['labelUrl'] ?? null,
            'message' => $data['message'] ?? 'Shipment created successfully',
        ];
    }

    /**
     * Transform Envia.com tracking response to our format
     */
    protected function transformFromEnviaTracking(array $data): array
    {
        $events = [];
        
        if (isset($data['tracking']) && is_array($data['tracking'])) {
            foreach ($data['tracking'] as $event) {
                $events[] = [
                    'date' => $event['date'] ?? '',
                    'time' => $event['time'] ?? '',
                    'status' => $event['status'] ?? '',
                    'description' => $event['description'] ?? '',
                    'location' => $event['location'] ?? '',
                ];
            }
        }

        return [
            'success' => !empty($events),
            'tracking_number' => $data['trackingNumber'] ?? '',
            'status' => $data['status'] ?? 'unknown',
            'carrier' => $data['carrier'] ?? '',
            'events' => $events,
            'current_location' => $data['currentLocation'] ?? '',
            'estimated_delivery' => $data['estimatedDelivery'] ?? null,
            'delivered_date' => $data['deliveredDate'] ?? null,
        ];
    }

    /**
     * Transform Envia.com carriers response to our format
     */
    protected function transformFromEnviaCarriers(array $data): array
    {
        $carriers = [];
        
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $carrier) {
                $carriers[] = [
                    'code' => $carrier['code'] ?? '',
                    'name' => $carrier['name'] ?? '',
                    'logo' => $carrier['logo'] ?? '',
                    'services' => $carrier['services'] ?? [],
                    'countries' => $carrier['countries'] ?? [],
                ];
            }
        }

        return [
            'success' => !empty($carriers),
            'carriers' => $carriers,
        ];
    }

    /**
     * Test connection with Envia.com API
     */
    public function testConnection(): array
    {
        // For sandbox/development mode, return mock success
        if ($this->config['sandbox'] ?? true) {
            return [
                'success' => true,
                'message' => 'Sandbox mode: Connection test simulated successfully',
                'environment' => 'test',
                'api_url' => $this->config['api_url'],
                'auth_method' => 'mock'
            ];
        }

        try {
            $token = $this->authenticate();
            
            return [
                'success' => true,
                'message' => 'Connection established successfully',
                'token_length' => strlen($token),
                'environment' => 'production'
            ];
        } catch (MessagingShippingException $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get mock shipping rates for sandbox mode
     */
    private function getMockShippingRates(array $shipmentData): array
    {
        $mockRates = [
            [
                'service' => 'Envío Estándar',
                'service_code' => 'standard',
                'price' => 15000, // $150 COP
                'currency' => 'COP',
                'delivery_time' => '3-5 días hábiles',
                'estimated_delivery' => now()->addDays(4)->format('Y-m-d'),
                'carrier' => 'Envia.com'
            ],
            [
                'service' => 'Envío Express',
                'service_code' => 'express',
                'price' => 25000, // $250 COP
                'currency' => 'COP',
                'delivery_time' => '1-2 días hábiles',
                'estimated_delivery' => now()->addDays(2)->format('Y-m-d'),
                'carrier' => 'Envia.com'
            ]
        ];

        return [
            'success' => true,
            'rates' => $mockRates,
            'sandbox' => true
        ];
    }

    /**
     * Format shipment data for Envia.com API
     */
    private function formatShipmentForEnvia(array $shipmentData): array
    {
        return [
            'carrier_code' => 'standard',
            'service_type' => 'ground',
            'origin' => [
                'name' => 'Sender Name',
                'company' => 'Sender Company',
                'email' => 'sender@example.com',
                'phone' => '+57123456789',
                'address' => $shipmentData['origin']['address'] ?? '',
                'city' => $shipmentData['origin']['city'] ?? '',
                'state' => $shipmentData['origin']['state'] ?? '',
                'postal_code' => $shipmentData['origin']['postal_code'] ?? '',
                'country' => $shipmentData['origin']['country'] ?? 'CO',
            ],
            'destination' => [
                'name' => 'Recipient Name',
                'company' => 'Recipient Company',
                'email' => 'recipient@example.com',
                'phone' => '+57987654321',
                'address' => $shipmentData['destination']['address'] ?? '',
                'city' => $shipmentData['destination']['city'] ?? '',
                'state' => $shipmentData['destination']['state'] ?? '',
                'postal_code' => $shipmentData['destination']['postal_code'] ?? '',
                'country' => $shipmentData['destination']['country'] ?? 'CO',
            ],
            'packages' => collect($shipmentData['packages'] ?? [])->map(function ($package) {
                return [
                    'weight' => $package['weight'] ?? 1,
                    'length' => $package['length'] ?? 10,
                    'width' => $package['width'] ?? 10,
                    'height' => $package['height'] ?? 10,
                    'declared_value' => $package['declared_value'] ?? 10000,
                ];
            })->toArray(),
        ];
    }

    /**
     * Format Envia.com rates response
     */
    private function formatEnviaRates(array $data): array
    {
        return collect($data['rates'] ?? [])->map(function ($rate) {
            return [
                'service' => $rate['service_name'] ?? 'Standard',
                'service_code' => $rate['service_code'] ?? 'standard',
                'price' => $rate['price'] ?? 0,
                'currency' => $rate['currency'] ?? 'COP',
                'delivery_time' => $rate['delivery_time'] ?? '3-5 días hábiles',
                'estimated_delivery' => $rate['estimated_delivery'] ?? now()->addDays(3)->format('Y-m-d'),
                'carrier' => $rate['carrier'] ?? 'Envia.com'
            ];
        })->toArray();
    }
}
