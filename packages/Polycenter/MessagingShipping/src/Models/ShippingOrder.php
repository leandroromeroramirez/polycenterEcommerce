<?php

namespace Polycenter\MessagingShipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Webkul\Sales\Models\Order;

class ShippingOrder extends Model
{
    use HasFactory;

    protected $table = 'messaging_shipping_orders';

    protected $fillable = [
        'order_id',
        'api_order_id',
        'tracking_number',
        'status',
        'service_type',
        'estimated_delivery',
        'shipping_cost',
        'api_response',
        'notes',
    ];

    protected $casts = [
        'estimated_delivery' => 'datetime',
        'shipping_cost' => 'decimal:2',
        'api_response' => 'array',
    ];

    /**
     * Get the order that owns the shipping order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the status badge color
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'confirmed' => 'info',
            'picked_up' => 'primary',
            'in_transit' => 'primary',
            'out_for_delivery' => 'success',
            'delivered' => 'success',
            'failed' => 'danger',
            'cancelled' => 'danger',
            'returned' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Get the status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            'picked_up' => 'Recogido',
            'in_transit' => 'En Tránsito',
            'out_for_delivery' => 'En Entrega',
            'delivered' => 'Entregado',
            'failed' => 'Fallido',
            'cancelled' => 'Cancelado',
            'returned' => 'Devuelto',
            default => 'Desconocido',
        };
    }

    /**
     * Check if the shipping order can be cancelled
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }

    /**
     * Check if the shipping order is in transit
     */
    public function isInTransit(): bool
    {
        return in_array($this->status, ['picked_up', 'in_transit', 'out_for_delivery']);
    }

    /**
     * Check if the shipping order is completed
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['delivered', 'returned']);
    }

    /**
     * Check if the shipping order has failed
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, ['failed', 'cancelled']);
    }

    /**
     * Get formatted shipping cost
     */
    public function getFormattedShippingCostAttribute(): string
    {
        if (!$this->shipping_cost) {
            return 'N/A';
        }

        return core()->currency($this->shipping_cost, $this->currency ?? core()->getCurrentCurrencyCode());
    }

    /**
     * Get estimated delivery in human readable format
     */
    public function getFormattedEstimatedDeliveryAttribute(): ?string
    {
        if (!$this->estimated_delivery) {
            return null;
        }

        return $this->estimated_delivery->format('d/m/Y H:i');
    }

    /**
     * Get actual delivery in human readable format
     */
    public function getFormattedActualDeliveryAttribute(): ?string
    {
        if (!$this->actual_delivery) {
            return null;
        }

        return $this->actual_delivery->format('d/m/Y H:i');
    }

    /**
     * Scope for filtering by status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by service type
     */
    public function scopeWithServiceType($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    /**
     * Scope for pending shipments
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }

    /**
     * Scope for in transit shipments
     */
    public function scopeInTransit($query)
    {
        return $query->whereIn('status', ['picked_up', 'in_transit', 'out_for_delivery']);
    }

    /**
     * Scope for completed shipments
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['delivered', 'returned']);
    }

    /**
     * Scope for failed shipments
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'cancelled']);
    }

    /**
     * Add a delivery attempt
     */
    public function addDeliveryAttempt(array $attemptData = []): void
    {
        $this->increment('delivery_attempts');
        
        $webhookData = $this->webhook_data ?? [];
        $webhookData[] = array_merge([
            'type' => 'delivery_attempt',
            'timestamp' => now()->toISOString(),
        ], $attemptData);
        
        $this->update(['webhook_data' => $webhookData]);
    }

    /**
     * Mark as delivered
     */
    public function markAsDelivered(\DateTime $deliveryTime = null): void
    {
        $this->update([
            'status' => 'delivered',
            'actual_delivery' => $deliveryTime ?? now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $reason = ''): void
    {
        $this->update([
            'status' => 'failed',
            'notes' => $this->notes . "\nFailed: " . $reason,
        ]);
    }

    /**
     * Get tracking URL if available
     */
    public function getTrackingUrlAttribute(): ?string
    {
        if (!$this->tracking_number) {
            return null;
        }

        // This would be the tracking URL format from the messaging service
        $baseUrl = config('messaging-shipping.tracking_url', 'https://track.messaging-service.com');
        return $baseUrl . '/track/' . $this->tracking_number;
    }
}
