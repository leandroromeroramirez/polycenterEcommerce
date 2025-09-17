<?php

namespace Polycenter\SIIGO\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Polycenter\SIIGO\Exceptions\SIIGOApiException;
use Polycenter\SIIGO\Models\SIIGOMapping;

class SIIGOService
{
    protected Client $client;
    protected ?string $accessToken = null;
    protected array $config;

    public function __construct()
    {
        $this->config = config('siigo');
        $this->client = new Client([
            'base_uri' => $this->config['api_url'],
            'timeout' => $this->config['timeout'],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * Autenticar con SIIGO API
     */
    public function authenticate(): bool
    {
        try {
            $response = $this->client->post($this->config['endpoints']['auth'], [
                'json' => [
                    'username' => $this->config['username'],
                    'access_key' => $this->config['access_key'],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                
                // Guardar token en caché
                if ($this->config['cache_enabled']) {
                    Cache::put('siigo_access_token', $this->accessToken, $this->config['cache_ttl']);
                }
                
                $this->logRequest('AUTH', 'Authentication successful');
                return true;
            }

            return false;
        } catch (GuzzleException $e) {
            $this->logError('AUTH', $e->getMessage());
            throw new SIIGOApiException('Authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * Obtener token de acceso
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        // Intentar obtener del caché
        if ($this->config['cache_enabled']) {
            $cachedToken = Cache::get('siigo_access_token');
            if ($cachedToken) {
                $this->accessToken = $cachedToken;
                return $this->accessToken;
            }
        }

        // Si no hay token, autenticar
        if (!$this->authenticate()) {
            throw new SIIGOApiException('Unable to obtain access token');
        }

        return $this->accessToken;
    }

    /**
     * Realizar petición autenticada
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getAccessToken();
        
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ];

        if (!empty($data)) {
            $options['json'] = $data;
        }

        try {
            $response = $this->client->request($method, $endpoint, $options);
            $responseData = json_decode($response->getBody()->getContents(), true);

            $this->logRequest($method . ' ' . $endpoint, 'Success', $data, $responseData);
            
            return $responseData;
        } catch (GuzzleException $e) {
            $this->logError($method . ' ' . $endpoint, $e->getMessage(), $data);
            throw new SIIGOApiException('API request failed: ' . $e->getMessage());
        }
    }

    /**
     * Crear cliente en SIIGO
     */
    public function createCustomer(array $customerData): array
    {
        $entityId = $customerData['id'] ?? null;
        
        try {
            // Verificar si ya existe un mapeo
            if ($entityId) {
                $mapping = SIIGOMapping::getMapping('customer', $entityId);
                if ($mapping && $mapping->isSynced()) {
                    Log::info('Customer already synced', ['entity_id' => $entityId, 'siigo_id' => $mapping->siigo_id]);
                    return ['id' => $mapping->siigo_id, 'status' => 'already_synced'];
                }
            }

            $mappedData = $this->mapCustomerData($customerData);
            $response = $this->makeRequest('POST', $this->config['endpoints']['customers'], $mappedData);
            
            // Guardar mapeo exitoso
            if ($entityId && isset($response['id'])) {
                SIIGOMapping::updateMapping('customer', $entityId, $response['id'], 'synced', $response);
            }
            
            return $response;
        } catch (\Exception $e) {
            // Guardar mapeo fallido
            if ($entityId) {
                SIIGOMapping::updateMapping('customer', $entityId, null, 'failed', null, $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Actualizar cliente en SIIGO
     */
    public function updateCustomer(string $customerId, array $customerData): array
    {
        $mappedData = $this->mapCustomerData($customerData);
        return $this->makeRequest('PUT', $this->config['endpoints']['customers'] . '/' . $customerId, $mappedData);
    }

    /**
     * Obtener cliente de SIIGO
     */
    public function getCustomer(string $customerId): array
    {
        return $this->makeRequest('GET', $this->config['endpoints']['customers'] . '/' . $customerId);
    }

    /**
     * Crear producto en SIIGO
     */
    public function createProduct(array $productData): array
    {
        $entityId = $productData['id'] ?? null;
        
        try {
            // Verificar si ya existe un mapeo
            if ($entityId) {
                $mapping = SIIGOMapping::getMapping('product', $entityId);
                if ($mapping && $mapping->isSynced()) {
                    Log::info('Product already synced', ['entity_id' => $entityId, 'siigo_id' => $mapping->siigo_id]);
                    return ['id' => $mapping->siigo_id, 'status' => 'already_synced'];
                }
            }

            $mappedData = $this->mapProductData($productData);
            $response = $this->makeRequest('POST', $this->config['endpoints']['products'], $mappedData);
            
            // Guardar mapeo exitoso
            if ($entityId && isset($response['id'])) {
                SIIGOMapping::updateMapping('product', $entityId, $response['id'], 'synced', $response);
            }
            
            return $response;
        } catch (\Exception $e) {
            // Guardar mapeo fallido
            if ($entityId) {
                SIIGOMapping::updateMapping('product', $entityId, null, 'failed', null, $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Crear factura en SIIGO
     */
    public function createInvoice(array $invoiceData): array
    {
        return $this->makeRequest('POST', $this->config['endpoints']['invoices'], $invoiceData);
    }

    /**
     * Mapear datos de cliente de Bagisto a SIIGO
     */
    protected function mapCustomerData(array $bagistoData): array
    {
        $mapping = $this->config['field_mapping']['customer'];
        $siigoData = [];

        foreach ($mapping as $siigoField => $bagistoField) {
            if (isset($bagistoData[$bagistoField])) {
                $siigoData[$siigoField] = $bagistoData[$bagistoField];
            }
        }

        return $siigoData;
    }

    /**
     * Mapear datos de producto de Bagisto a SIIGO
     */
    protected function mapProductData(array $bagistoData): array
    {
        $mapping = $this->config['field_mapping']['product'];
        $siigoData = [];

        foreach ($mapping as $siigoField => $bagistoField) {
            if (isset($bagistoData[$bagistoField])) {
                $siigoData[$siigoField] = $bagistoData[$bagistoField];
            }
        }

        return $siigoData;
    }

    /**
     * Log de peticiones
     */
    protected function logRequest(string $action, string $message, array $request = [], array $response = []): void
    {
        if ($this->config['log_requests']) {
            Log::info('SIIGO API Request', [
                'action' => $action,
                'message' => $message,
                'request' => $this->config['log_requests'] ? $request : 'Logging disabled',
                'response' => $this->config['log_responses'] ? $response : 'Logging disabled',
            ]);
        }
    }

    /**
     * Log de errores
     */
    protected function logError(string $action, string $error, array $request = []): void
    {
        Log::error('SIIGO API Error', [
            'action' => $action,
            'error' => $error,
            'request' => $request,
        ]);
    }
}
