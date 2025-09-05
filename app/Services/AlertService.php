<?php

namespace App\Services;

use App\Mail\LowStockAlert;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AlertService
{
    public function sendLowStockAlert($lowStockProducts, $state = 'All States', $recipients = null)
    {
        try {
            if (empty($lowStockProducts)) {
                Log::info('No low stock products to alert about');
                return false;
            }

            $recipients = $recipients ?: ['admin@vitalvida.com'];
            
            $mailable = new LowStockAlert($lowStockProducts, null, $state);

            foreach ($recipients as $recipient) {
                Mail::to($recipient)->send($mailable);
                Log::info("Low stock alert sent to: {$recipient}");
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send low stock alert: ' . $e->getMessage());
            return false;
        }
    }

    public function sendTestAlert($recipient = 'test@vitalvida.com')
    {
        $testProducts = [
            [
                'product_id' => 1,
                'current_stock' => 2,
                'threshold' => 10,
                'shortage' => 8,
                'state' => 'Lagos'
            ],
            [
                'product_id' => 2,
                'current_stock' => 0,
                'threshold' => 15,
                'shortage' => 15,
                'state' => 'Lagos'
            ]
        ];

        try {
            $mailable = new LowStockAlert($testProducts, 2, 'Lagos (Test)');
            Mail::to($recipient)->send($mailable);
            
            Log::info("Test alert sent to: {$recipient}");
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to send test alert: ' . $e->getMessage());
            return false;
        }
    }
}
