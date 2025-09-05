<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\FraudPattern;
use App\Models\PaymentMismatch;
use App\Models\Order;
use App\Models\Staff;
use App\Models\DeliveryAgent;
use App\Models\DaInventory;
use Illuminate\Support\Facades\Log;

class FraudDetectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting automated fraud detection job');
        
        // Run different fraud detection methods
        $this->detectPaymentFraud();
        $this->detectDeliveryFraud();
        $this->detectGhostOrderPatterns();
        $this->detectInventoryFraud();
        
        // Send notifications for critical findings
        $this->sendCriticalNotifications();
        
        Log::info('Automated fraud detection job completed');
    }

    /**
     * Detect payment fraud patterns
     */
    private function detectPaymentFraud(): void
    {
        // Find staff who marked orders as paid but have no Moniepoint confirmations
        $suspiciousPayments = PaymentMismatch::where('resolution_status', 'pending')
            ->where('created_at', '>=', now()->subDays(7))
            ->get()
            ->groupBy('staff_claimed_by');

        foreach ($suspiciousPayments as $staffId => $mismatches) {
            $totalAmount = $mismatches->sum('amount_difference');
            $count = $mismatches->count();
            
            // Calculate confidence based on pattern severity
            $confidence = min(95, 70 + ($count * 5));
            
            // Check if pattern already exists
            $existingPattern = FraudPattern::where('staff_id', $staffId)
                ->where('type', 'PAYMENT_FRAUD')
                ->where('status', '!=', 'RESOLVED')
                ->first();

            if (!$existingPattern) {
                FraudPattern::create([
                    'type' => 'PAYMENT_FRAUD',
                    'staff_id' => $staffId,
                    'confidence_score' => $confidence,
                    'risk_amount' => $totalAmount,
                    'detected_at' => now(),
                    'status' => $confidence > 90 ? 'AUTO_BLOCKED' : 'INVESTIGATING',
                    'evidence' => [
                        'payment_claims' => $count,
                        'total_amount' => $totalAmount,
                        'moniepoint_confirmations' => 0,
                        'timeframe' => '7 days',
                    ],
                    'auto_action_taken' => $confidence > 90 ? 'Staff auto-blocked' : 'Enhanced monitoring activated',
                    'gm_notified' => false,
                ]);
            }
        }
    }

    /**
     * Detect delivery fraud patterns
     */
    private function detectDeliveryFraud(): void
    {
        // Find DAs with decreasing stock but no OTP confirmations
        $suspiciousDAs = DeliveryAgent::whereHas('inventory', function($q) {
            $q->where('days_stagnant', '>', 5);
        })->whereDoesntHave('orders', function($q) {
            $q->where('created_at', '>=', now()->subDays(5))
              ->whereNotNull('otp_code');
        })->get();

        foreach ($suspiciousDAs as $da) {
            $stockValue = $da->inventory->sum(function($item) {
                return $item->quantity * 2500; // Average product price
            });

            // Check if pattern already exists
            $existingPattern = FraudPattern::where('staff_id', $da->user_id)
                ->where('type', 'DELIVERY_FRAUD')
                ->where('status', '!=', 'RESOLVED')
                ->first();

            if (!$existingPattern) {
                FraudPattern::create([
                    'type' => 'DELIVERY_FRAUD',
                    'staff_id' => $da->user_id,
                    'confidence_score' => 89,
                    'risk_amount' => $stockValue,
                    'detected_at' => now(),
                    'status' => 'INVESTIGATING',
                    'evidence' => [
                        'days_no_movement' => $da->inventory->max('days_stagnant'),
                        'stock_value' => $stockValue,
                        'otp_confirmations' => 0,
                        'da_state' => $da->state,
                    ],
                    'auto_action_taken' => 'Stock replenishment blocked',
                    'gm_notified' => false,
                ]);
            }
        }
    }

    /**
     * Detect ghost order patterns
     */
    private function detectGhostOrderPatterns(): void
    {
        // Find phone numbers with multiple orders but different names
        $suspiciousOrders = Order::selectRaw('customer_phone, COUNT(*) as order_count, COUNT(DISTINCT customer_name) as name_count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('customer_phone')
            ->having('order_count', '>', 3)
            ->having('name_count', '>', 1)
            ->get();

        foreach ($suspiciousOrders as $order) {
            $confidence = min(95, 60 + ($order->order_count * 5));
            
            FraudPattern::create([
                'type' => 'GHOST_ORDER_PATTERN',
                'staff_id' => null,
                'confidence_score' => $confidence,
                'risk_amount' => 0, // Risk amount calculated separately
                'detected_at' => now(),
                'status' => 'INVESTIGATING',
                'evidence' => [
                    'phone' => $order->customer_phone,
                    'order_count' => $order->order_count,
                    'name_count' => $order->name_count,
                    'timeframe' => '30 days',
                ],
                'auto_action_taken' => 'Phone number flagged for monitoring',
                'gm_notified' => false,
            ]);
        }
    }

    /**
     * Detect inventory fraud patterns
     */
    private function detectInventoryFraud(): void
    {
        // Find DAs with suspicious inventory patterns
        $suspiciousInventory = DaInventory::where('quantity', '>', 100)
            ->where('days_stagnant', '>', 10)
            ->with(['deliveryAgent'])
            ->get();

        foreach ($suspiciousInventory as $inventory) {
            $stockValue = $inventory->quantity * 2500; // Average product price
            
            FraudPattern::create([
                'type' => 'INVENTORY_FRAUD',
                'staff_id' => $inventory->deliveryAgent->user_id,
                'confidence_score' => 75,
                'risk_amount' => $stockValue,
                'detected_at' => now(),
                'status' => 'INVESTIGATING',
                'evidence' => [
                    'product_type' => $inventory->product_type,
                    'quantity' => $inventory->quantity,
                    'days_stagnant' => $inventory->days_stagnant,
                    'stock_value' => $stockValue,
                ],
                'auto_action_taken' => 'Inventory audit scheduled',
                'gm_notified' => false,
            ]);
        }
    }

    /**
     * Send critical notifications
     */
    private function sendCriticalNotifications(): void
    {
        $criticalPatterns = FraudPattern::where('confidence_score', '>=', 90)
            ->where('gm_notified', false)
            ->get();

        foreach ($criticalPatterns as $pattern) {
            // Send WhatsApp/SMS alert
            $this->sendFraudAlert($pattern);
            $pattern->update(['gm_notified' => true]);
        }
    }

    /**
     * Send fraud alert
     */
    private function sendFraudAlert($pattern): void
    {
        $message = "🚨 CRITICAL FRAUD ALERT: {$pattern->type} detected with {$pattern->confidence_score}% confidence. Risk: ₦" . number_format($pattern->risk_amount) . ". Action: {$pattern->auto_action_taken}";
        
        // Integration with eBulkSMS would go here
        // For now, we'll log it
        Log::info("Fraud alert sent for pattern {$pattern->id}: {$message}");
        
        // You could also dispatch a notification job here
        // Notification::route('whatsapp', '+234XXXXXXXXX')->notify(new FraudAlertNotification($pattern));
    }
}
