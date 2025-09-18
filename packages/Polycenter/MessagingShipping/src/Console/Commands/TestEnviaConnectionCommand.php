<?php

namespace Polycenter\MessagingShipping\Console\Commands;

use Illuminate\Console\Command;
use Polycenter\MessagingShipping\Services\MessagingShippingService;

class TestEnviaConnectionCommand extends Command
{
    protected $signature = 'messaging-shipping:test-envia';
    protected $description = 'Test connection with Envia.com API';

    protected MessagingShippingService $shippingService;

    public function __construct(MessagingShippingService $shippingService)
    {
        parent::__construct();
        $this->shippingService = $shippingService;
    }

    public function handle()
    {
        $this->info('Testing connection with Envia.com API...');

        // Test connection
        $connectionTest = $this->shippingService->testConnection();
        
        if ($connectionTest['success']) {
            $this->info('✅ Connection test successful!');
            $this->line('Message: ' . $connectionTest['message']);
        } else {
            $this->error('❌ Connection test failed!');
            $this->line('Error: ' . $connectionTest['error']);
            return 1;
        }

        // Test getting carriers
        $this->info('Getting available carriers...');
        $carriers = $this->shippingService->getAvailableCarriers();
        
        if ($carriers['success']) {
            $this->info('✅ Carriers retrieved successfully!');
            $this->table(
                ['Carrier', 'Service', 'Status'],
                collect($carriers['carriers'])->map(function ($carrier) {
                    return [
                        $carrier['name'] ?? 'N/A',
                        $carrier['service'] ?? 'N/A',
                        $carrier['active'] ? 'Active' : 'Inactive'
                    ];
                })->toArray()
            );
        } else {
            $this->warn('⚠️ Could not retrieve carriers');
            $this->line('Error: ' . $carriers['error']);
        }

        // Test shipping rate calculation
        $this->info('Testing shipping rate calculation...');
        
        $testShipment = [
            'origin' => [
                'postal_code' => '11001',
                'city' => 'Bogotá',
                'state' => 'Bogotá D.C.',
                'country' => 'CO',
            ],
            'destination' => [
                'postal_code' => '050001',
                'city' => 'Medellín',
                'state' => 'Antioquia',
                'country' => 'CO',
            ],
            'packages' => [
                [
                    'weight' => 1.5,
                    'length' => 20,
                    'width' => 15,
                    'height' => 10,
                    'declared_value' => 100000,
                ]
            ]
        ];

        $rates = $this->shippingService->getShippingRates($testShipment);
        
        if ($rates['success']) {
            $this->info('✅ Shipping rates calculated successfully!');
            
            if (!empty($rates['rates'])) {
                $this->table(
                    ['Service', 'Price', 'Currency', 'Delivery Time'],
                    collect($rates['rates'])->map(function ($rate) {
                        return [
                            $rate['service'],
                            number_format($rate['price'], 2),
                            $rate['currency'],
                            $rate['delivery_time']
                        ];
                    })->toArray()
                );
            } else {
                $this->warn('No rates available for this shipment');
            }
        } else {
            $this->error('❌ Could not calculate shipping rates');
            $this->line('Error: ' . $rates['error']);
        }

        $this->info('Test completed!');
        return 0;
    }
}
