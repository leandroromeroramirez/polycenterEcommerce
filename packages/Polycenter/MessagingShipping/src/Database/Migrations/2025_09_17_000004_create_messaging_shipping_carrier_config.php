<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert default configuration for MessagingShipping carrier
        $configurations = [
            [
                'code' => 'sales.carriers.messaging_shipping.active',
                'value' => '1',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.title',
                'value' => 'MessagingShipping',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.description',
                'value' => 'Envíos rápidos y seguros con Envia.com',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.origin_city_code',
                'value' => '11001',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.origin_postal_code',
                'value' => '110111',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.origin_city',
                'value' => 'Bogotá',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.origin_state',
                'value' => 'Bogotá D.C.',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.origin_country',
                'value' => 'CO',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.default_length',
                'value' => '30',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.default_width',
                'value' => '20',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.default_height',
                'value' => '15',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'sales.carriers.messaging_shipping.sort_order',
                'value' => '1',
                'channel_code' => 'default',
                'locale_code' => 'en',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert configurations into core_config table
        foreach ($configurations as $config) {
            DB::table('core_config')->updateOrInsert(
                [
                    'code' => $config['code'],
                    'channel_code' => $config['channel_code'],
                    'locale_code' => $config['locale_code'],
                ],
                $config
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove MessagingShipping carrier configurations
        DB::table('core_config')
            ->where('code', 'like', 'sales.carriers.messaging_shipping.%')
            ->delete();
    }
};
