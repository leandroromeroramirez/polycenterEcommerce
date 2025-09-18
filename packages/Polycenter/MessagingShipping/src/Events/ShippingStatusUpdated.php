<?php

namespace Polycenter\MessagingShipping\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Polycenter\MessagingShipping\Models\ShippingOrder;

class ShippingStatusUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ShippingOrder $shippingOrder,
        public array $webhookData
    ) {}
}
