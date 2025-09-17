<?php

namespace Polycenter\SIIGO\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SIIGOMapping extends Model
{
    use HasFactory;

    protected $table = 'siigo_mappings';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'siigo_id',
        'sync_status',
        'last_synced_at',
        'sync_data',
        'error_message'
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'sync_data' => 'array',
    ];

    /**
     * Get mapping for a specific entity
     */
    public static function getMapping(string $entityType, int $entityId): ?self
    {
        return static::where('entity_type', $entityType)
                    ->where('entity_id', $entityId)
                    ->first();
    }

    /**
     * Create or update mapping
     */
    public static function updateMapping(
        string $entityType, 
        int $entityId, 
        ?string $siigoId = null,
        string $status = 'pending',
        ?array $syncData = null,
        ?string $errorMessage = null
    ): self {
        return static::updateOrCreate(
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'siigo_id' => $siigoId,
                'sync_status' => $status,
                'last_synced_at' => now(),
                'sync_data' => $syncData,
                'error_message' => $errorMessage,
            ]
        );
    }

    /**
     * Mark as synced successfully
     */
    public function markAsSynced(string $siigoId, ?array $syncData = null): void
    {
        $this->update([
            'siigo_id' => $siigoId,
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'sync_data' => $syncData,
            'error_message' => null,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'sync_status' => 'failed',
            'last_synced_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Check if entity is already synced
     */
    public function isSynced(): bool
    {
        return $this->sync_status === 'synced' && !empty($this->siigo_id);
    }

    /**
     * Scope for getting failed syncs
     */
    public function scopeFailed($query)
    {
        return $query->where('sync_status', 'failed');
    }

    /**
     * Scope for getting synced entities
     */
    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced');
    }

    /**
     * Scope for getting pending syncs
     */
    public function scopePending($query)
    {
        return $query->where('sync_status', 'pending');
    }
}
