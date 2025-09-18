<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messaging_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('rate_id')->unique();
            $table->string('service_type');
            $table->decimal('price', 10, 2);
            $table->decimal('base_price', 10, 2);
            $table->integer('estimated_days')->nullable();
            $table->timestamp('estimated_delivery')->nullable();
            $table->boolean('tracking_available')->default(true);
            $table->boolean('insurance_available')->default(false);
            $table->json('origin_data');
            $table->json('destination_data');
            $table->json('packages_data');
            $table->decimal('declared_value', 10, 2);
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['service_type', 'expires_at']);
            $table->index('rate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messaging_shipping_rates');
    }
};
