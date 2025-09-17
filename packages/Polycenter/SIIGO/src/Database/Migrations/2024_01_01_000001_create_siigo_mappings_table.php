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
        Schema::create('siigo_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type'); // 'customer', 'product', 'order', etc.
            $table->unsignedBigInteger('entity_id'); // ID de la entidad en Bagisto
            $table->string('siigo_id')->nullable(); // ID de la entidad en SIIGO
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_data')->nullable(); // Datos adicionales de sincronización
            $table->text('error_message')->nullable(); // Mensaje de error si falla
            $table->timestamps();

            // Índices para mejorar el rendimiento
            $table->unique(['entity_type', 'entity_id']);
            $table->index(['entity_type', 'sync_status']);
            $table->index('siigo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siigo_mappings');
    }
};
