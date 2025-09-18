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
        Schema::create('messaging_shipping_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_id');
            $table->string('api_order_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->enum('status', [
                'pending',
                'confirmed',
                'picked_up',
                'in_transit',
                'out_for_delivery',
                'delivered',
                'cancelled',
                'failed',
                'returned'
            ])->default('pending');
            $table->string('service_type');
            $table->decimal('shipping_cost', 10, 2);
            $table->timestamp('estimated_delivery')->nullable();
            $table->json('api_response')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index(['order_id', 'status']);
            $table->index('tracking_number');
            $table->index('api_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messaging_shipping_orders');
    }
};
