<?php

namespace Polycenter\MessagingShipping\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Polycenter\MessagingShipping\Services\MessagingShippingService;
use Polycenter\MessagingShipping\Services\OrderShippingService;
use Polycenter\MessagingShipping\Models\ShippingOrder;
use Webkul\Admin\Http\Controllers\Controller;

class MessagingShippingController extends Controller
{
    public function __construct(
        protected MessagingShippingService $messagingService,
        protected OrderShippingService $orderShippingService
    ) {}

    /**
     * Display the main messaging shipping dashboard
     */
    public function index(): View
    {
        $shippingOrders = ShippingOrder::with('order')
            ->latest()
            ->paginate(15);

        $stats = [
            'total_orders' => ShippingOrder::count(),
            'pending_orders' => ShippingOrder::pending()->count(),
            'in_transit_orders' => ShippingOrder::inTransit()->count(),
            'completed_orders' => ShippingOrder::completed()->count(),
            'failed_orders' => ShippingOrder::failed()->count(),
        ];

        return view('messaging-shipping::admin.index', compact('shippingOrders', 'stats'));
    }

    /**
     * Display settings page
     */
    public function settings(): View
    {
        $config = config('messaging-shipping');
        
        return view('messaging-shipping::admin.settings', compact('config'));
    }

    /**
     * Save settings
     */
    public function saveSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
            'api_url' => 'required|url',
            'sandbox' => 'boolean',
        ]);

        try {
            // Update environment file or configuration
            $this->updateEnvironmentVariables([
                'MESSAGING_SHIPPING_API_KEY' => $request->api_key,
                'MESSAGING_SHIPPING_API_SECRET' => $request->api_secret,
                'MESSAGING_SHIPPING_API_URL' => $request->api_url,
                'MESSAGING_SHIPPING_SANDBOX' => $request->sandbox ? 'true' : 'false',
            ]);

            session()->flash('success', 'Messaging Shipping settings saved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to save messaging shipping settings', [
                'error' => $e->getMessage(),
            ]);
            
            session()->flash('error', 'Failed to save settings: ' . $e->getMessage());
        }

        return redirect()->route('admin.messaging-shipping.settings');
    }

    /**
     * Test API connection
     */
    public function testConnection(): RedirectResponse
    {
        try {
            $result = $this->messagingService->testConnection();
            
            if ($result['success']) {
                session()->flash('success', 'Connection to Messaging Shipping API successful!');
            } else {
                session()->flash('error', 'Failed to connect to Messaging Shipping API: ' . ($result['message'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('Messaging Shipping connection test failed', [
                'error' => $e->getMessage(),
            ]);
            
            session()->flash('error', 'Connection failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.messaging-shipping.index');
    }

    /**
     * Show shipping order details
     */
    public function show(ShippingOrder $shippingOrder): View
    {
        $shippingOrder->load('order.items', 'order.shipping_address', 'order.billing_address');
        
        // Get latest tracking info if available
        $trackingInfo = null;
        if ($shippingOrder->tracking_number) {
            try {
                $trackingInfo = $this->messagingService->getTrackingInfo($shippingOrder->tracking_number);
            } catch (\Exception $e) {
                Log::warning('Failed to get tracking info', [
                    'shipping_order_id' => $shippingOrder->id,
                    'tracking_number' => $shippingOrder->tracking_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return view('messaging-shipping::admin.show', compact('shippingOrder', 'trackingInfo'));
    }

    /**
     * Cancel shipping order
     */
    public function cancel(Request $request, ShippingOrder $shippingOrder): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $success = $this->orderShippingService->cancelShippingOrder(
                $shippingOrder->order,
                $request->reason
            );

            if ($success) {
                session()->flash('success', 'Shipping order cancelled successfully');
            } else {
                session()->flash('error', 'Failed to cancel shipping order');
            }
        } catch (\Exception $e) {
            Log::error('Failed to cancel shipping order', [
                'shipping_order_id' => $shippingOrder->id,
                'error' => $e->getMessage(),
            ]);
            
            session()->flash('error', 'Failed to cancel shipping order: ' . $e->getMessage());
        }

        return redirect()->route('admin.messaging-shipping.show', $shippingOrder);
    }

    /**
     * Refresh shipping order status
     */
    public function refreshStatus(ShippingOrder $shippingOrder): RedirectResponse
    {
        if (!$shippingOrder->api_order_id) {
            session()->flash('error', 'No API order ID available for this shipping order');
            return redirect()->back();
        }

        try {
            // Use tracking info to get status if tracking number is available
            if ($shippingOrder->tracking_number) {
                $status = $this->messagingService->getTrackingInfo($shippingOrder->tracking_number);
                
                // Update local status
                $shippingOrder->update([
                    'status' => $status['status'] ?? $shippingOrder->status,
                    'tracking_number' => $status['tracking_number'] ?? $shippingOrder->tracking_number,
                    'estimated_delivery' => isset($status['estimated_delivery']) ? 
                        Carbon::parse($status['estimated_delivery']) : 
                        $shippingOrder->estimated_delivery,
                ]);

                session()->flash('success', 'Shipping order status updated successfully');
            } else {
                session()->flash('error', 'No tracking number available for this shipping order');
            }
        } catch (\Exception $e) {
            Log::error('Failed to refresh shipping order status', [
                'shipping_order_id' => $shippingOrder->id,
                'error' => $e->getMessage(),
            ]);
            
            session()->flash('error', 'Failed to refresh status: ' . $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Bulk actions for shipping orders
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:refresh_status,export',
            'selected_orders' => 'required|array|min:1',
            'selected_orders.*' => 'exists:messaging_shipping_orders,id',
        ]);

        $selectedOrders = ShippingOrder::whereIn('id', $request->selected_orders)->get();
        $action = $request->action;

        try {
            switch ($action) {
                case 'refresh_status':
                    $this->bulkRefreshStatus($selectedOrders);
                    session()->flash('success', 'Status refreshed for selected orders');
                    break;
                    
                case 'export':
                    return $this->exportShippingOrders($selectedOrders);
                    
                default:
                    session()->flash('error', 'Invalid action selected');
            }
        } catch (\Exception $e) {
            Log::error('Bulk action failed', [
                'action' => $action,
                'orders_count' => $selectedOrders->count(),
                'error' => $e->getMessage(),
            ]);
            
            session()->flash('error', 'Bulk action failed: ' . $e->getMessage());
        }

        return redirect()->back();
    }

    /**
     * Bulk refresh status for multiple orders
     */
    protected function bulkRefreshStatus($shippingOrders): void
    {
        foreach ($shippingOrders as $shippingOrder) {
            if ($shippingOrder->tracking_number) {
                try {
                    $status = $this->messagingService->getTrackingInfo($shippingOrder->tracking_number);
                    
                    $shippingOrder->update([
                        'status' => $status['status'] ?? $shippingOrder->status,
                        'tracking_number' => $status['tracking_number'] ?? $shippingOrder->tracking_number,
                        'estimated_delivery' => isset($status['estimated_delivery']) ? 
                            Carbon::parse($status['estimated_delivery']) : 
                            $shippingOrder->estimated_delivery,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to refresh status for shipping order', [
                        'shipping_order_id' => $shippingOrder->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Export shipping orders to CSV
     */
    protected function exportShippingOrders($shippingOrders)
    {
        $filename = 'messaging_shipping_orders_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($shippingOrders) {
            $file = fopen('php://output', 'w');
            
            // CSV headers
            fputcsv($file, [
                'ID', 'Order ID', 'API Order ID', 'Tracking Number', 'Status',
                'Service Type', 'Shipping Cost', 'Estimated Delivery', 'Created At'
            ]);

            // CSV data
            foreach ($shippingOrders as $order) {
                fputcsv($file, [
                    $order->id,
                    $order->order->increment_id ?? $order->order_id,
                    $order->api_order_id,
                    $order->tracking_number,
                    $order->status_label,
                    $order->service_type,
                    $order->formatted_shipping_cost,
                    $order->formatted_estimated_delivery,
                    $order->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Update environment variables
     */
    protected function updateEnvironmentVariables(array $variables): void
    {
        $envPath = base_path('.env');
        
        if (!file_exists($envPath)) {
            throw new \Exception('.env file not found');
        }

        $envContent = file_get_contents($envPath);

        foreach ($variables as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        file_put_contents($envPath, $envContent);
    }
}
