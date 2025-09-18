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
        Schema::create('messaging_shipping_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_order_id');
            $table->string('tracking_number');
            $table->string('status');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('event_date');
            $table->json('raw_data')->nullable();
            $table->timestamps();
            
            $table->foreign('shipping_order_id')
                ->references('id')
                ->on('messaging_shipping_orders')
                ->onDelete('cascade');
            
            $table->index(['shipping_order_id', 'event_date']);
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messaging_shipping_tracking');
    }
};
