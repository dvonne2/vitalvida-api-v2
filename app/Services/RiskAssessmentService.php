<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Risk;
use App\Models\DeliveryAgent;
use App\Models\Revenue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RiskAssessmentService
{
    /**
     * Identify order anomalies
     */
    public function identifyOrderAnomalies(): array
    {
        $cacheKey = 'order_anomalies';
        
        return Cache::remember($cacheKey, 300, function () {
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            
            $anomalies = [];
            
            // Check for unusual order patterns
            $ordersToday = Order::whereDate('created_at', $today)->count();
            $ordersYesterday = Order::whereDate('created_at', $yesterday)->count();
            $avgOrders = Order::whereBetween('created_at', [
                Carbon::now()->subDays(7),
                Carbon::now()
            ])->count() / 7;
            
            // Anomaly: Sudden drop in orders
            if ($ordersToday < $avgOrders * 0.7) {
                $anomalies[] = [
                    'type' => 'order_drop',
                    'severity' => 'medium',
                    'description' => "Orders dropped by " . round((1 - $ordersToday / $avgOrders) * 100) . "%",
                    'impact' => 'Potential revenue loss',
                    'recommendation' => 'Check marketing campaigns and website functionality'
                ];
            }
            
            // Anomaly: Unusual order values
            $avgOrderValue = Order::whereDate('created_at', $today)->avg('total_amount') ?? 0;
            $normalAvgValue = Order::whereBetween('created_at', [
                Carbon::now()->subDays(30),
                Carbon::now()
            ])->avg('total_amount') ?? 0;
            
            if ($avgOrderValue > $normalAvgValue * 1.5) {
                $anomalies[] = [
                    'type' => 'high_order_values',
                    'severity' => 'low',
                    'description' => "Average order value increased by " . round((($avgOrderValue / $normalAvgValue) - 1) * 100) . "%",
                    'impact' => 'Potential fraud or pricing issues',
                    'recommendation' => 'Review recent orders for unusual patterns'
                ];
            }
            
            return $anomalies;
        });
    }

    /**
     * Detect fraud patterns
     */
    public function detectFraudPatterns(): array
    {
        $cacheKey = 'fraud_patterns';
        
        return Cache::remember($cacheKey, 600, function () {
            $today = Carbon::today();
            $fraudPatterns = [];
            
            // Check for multiple orders from same IP
            $ipPatterns = DB::table('orders')
                ->select('ip_address', DB::raw('count(*) as order_count'))
                ->whereDate('created_at', $today)
                ->groupBy('ip_address')
                ->having('order_count', '>', 3)
                ->get();
            
            foreach ($ipPatterns as $pattern) {
                $fraudPatterns[] = [
                    'type' => 'multiple_orders_same_ip',
                    'severity' => 'medium',
                    'description' => "{$pattern->order_count} orders from same IP",
                    'ip_address' => $pattern->ip_address,
                    'recommendation' => 'Review orders for potential fraud'
                ];
            }
            
            // Check for orders with payment mismatch
            $paymentMismatches = DB::table('orders')
                ->where('payment_status', 'paid')
                ->whereNull('payment_reference')
                ->whereDate('created_at', $today)
                ->count();
            
            if ($paymentMismatches > 0) {
                $fraudPatterns[] = [
                    'type' => 'payment_mismatch',
                    'severity' => 'high',
                    'description' => "{$paymentMismatches} orders marked paid without payment reference",
                    'impact' => 'Potential revenue loss',
                    'recommendation' => 'Immediate review of payment processing'
                ];
            }
            
            // Check for unusual refund patterns
            $refundRate = DB::table('orders')
                ->where('status', 'refunded')
                ->whereDate('created_at', $today)
                ->count() / max(1, Order::whereDate('created_at', $today)->count()) * 100;
            
            if ($refundRate > 5) {
                $fraudPatterns[] = [
                    'type' => 'high_refund_rate',
                    'severity' => 'medium',
                    'description' => "Refund rate of " . round($refundRate, 1) . "% today",
                    'impact' => 'Revenue and reputation risk',
                    'recommendation' => 'Review product quality and delivery processes'
                ];
            }
            
            return $fraudPatterns;
        });
    }

    /**
     * Assess inventory risks
     */
    public function assessInventoryRisks(): array
    {
        $cacheKey = 'inventory_risks';
        
        return Cache::remember($cacheKey, 1800, function () {
            $inventoryRisks = [];
            
            // Check for stockouts
            $stockouts = DB::table('inventory_items')
                ->where('quantity', 0)
                ->where('is_active', true)
                ->count();
            
            if ($stockouts > 0) {
                $inventoryRisks[] = [
                    'type' => 'stockout',
                    'severity' => 'high',
                    'description' => "{$stockouts} items out of stock",
                    'impact' => 'Lost sales opportunities',
                    'recommendation' => 'Restock immediately'
                ];
            }
            
            // Check for overstock
            $overstockItems = DB::table('inventory_items')
                ->where('quantity', '>', 100)
                ->where('last_movement', '<', Carbon::now()->subDays(30))
                ->count();
            
            if ($overstockItems > 0) {
                $inventoryRisks[] = [
                    'type' => 'overstock',
                    'severity' => 'medium',
                    'description' => "{$overstockItems} items overstocked",
                    'impact' => 'Capital tied up in inventory',
                    'recommendation' => 'Consider promotions or redistribution'
                ];
            }
            
            // Check for expiring products
            $expiringItems = DB::table('inventory_items')
                ->where('expiry_date', '<=', Carbon::now()->addDays(30))
                ->where('expiry_date', '>', Carbon::now())
                ->count();
            
            if ($expiringItems > 0) {
                $inventoryRisks[] = [
                    'type' => 'expiring_products',
                    'severity' => 'medium',
                    'description' => "{$expiringItems} items expiring soon",
                    'impact' => 'Potential waste and loss',
                    'recommendation' => 'Prioritize sales of expiring items'
                ];
            }
            
            return $inventoryRisks;
        });
    }

    /**
     * Calculate SLA breaches
     */
    public function calculateSLABreaches(): array
    {
        $cacheKey = 'sla_breaches';
        
        return Cache::remember($cacheKey, 300, function () {
            $today = Carbon::today();
            $slaBreaches = [];
            
            // Check delivery SLA breaches
            $deliveryBreaches = Order::where('status', 'out_for_delivery')
                ->where('created_at', '<', Carbon::now()->subHours(48))
                ->count();
            
            if ($deliveryBreaches > 0) {
                $slaBreaches[] = [
                    'type' => 'delivery_sla_breach',
                    'severity' => 'high',
                    'description' => "{$deliveryBreaches} orders exceed 48-hour delivery SLA",
                    'impact' => 'Customer dissatisfaction and potential refunds',
                    'recommendation' => 'Expedite delivery or contact customers'
                ];
            }
            
            // Check response time SLA breaches
            $responseBreaches = Order::where('status', 'pending')
                ->where('created_at', '<', Carbon::now()->subHours(24))
                ->count();
            
            if ($responseBreaches > 0) {
                $slaBreaches[] = [
                    'type' => 'response_sla_breach',
                    'severity' => 'medium',
                    'description' => "{$responseBreaches} orders exceed 24-hour response SLA",
                    'impact' => 'Poor customer experience',
                    'recommendation' => 'Process pending orders immediately'
                ];
            }
            
            // Check DA response SLA
            $daResponseBreaches = DeliveryAgent::where('last_activity', '<', Carbon::now()->subHours(4))
                ->where('status', 'active')
                ->count();
            
            if ($daResponseBreaches > 0) {
                $slaBreaches[] = [
                    'type' => 'da_response_breach',
                    'severity' => 'medium',
                    'description' => "{$daResponseBreaches} DAs inactive for >4 hours",
                    'impact' => 'Delivery delays',
                    'recommendation' => 'Contact inactive DAs'
                ];
            }
            
            return $slaBreaches;
        });
    }

    /**
     * Get comprehensive risk assessment
     */
    public function getRiskAssessment(): array
    {
        return [
            'order_anomalies' => $this->identifyOrderAnomalies(),
            'fraud_patterns' => $this->detectFraudPatterns(),
            'inventory_risks' => $this->assessInventoryRisks(),
            'sla_breaches' => $this->calculateSLABreaches(),
            'risk_score' => $this->calculateOverallRiskScore(),
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Calculate overall risk score
     */
    private function calculateOverallRiskScore(): int
    {
        $anomalies = $this->identifyOrderAnomalies();
        $fraudPatterns = $this->detectFraudPatterns();
        $inventoryRisks = $this->assessInventoryRisks();
        $slaBreaches = $this->calculateSLABreaches();
        
        $score = 0;
        
        // Weight different risk types
        foreach ($anomalies as $anomaly) {
            $score += $anomaly['severity'] === 'high' ? 3 : ($anomaly['severity'] === 'medium' ? 2 : 1);
        }
        
        foreach ($fraudPatterns as $pattern) {
            $score += $pattern['severity'] === 'high' ? 5 : ($pattern['severity'] === 'medium' ? 3 : 1);
        }
        
        foreach ($inventoryRisks as $risk) {
            $score += $risk['severity'] === 'high' ? 3 : ($risk['severity'] === 'medium' ? 2 : 1);
        }
        
        foreach ($slaBreaches as $breach) {
            $score += $breach['severity'] === 'high' ? 4 : ($breach['severity'] === 'medium' ? 2 : 1);
        }
        
        return min(100, $score);
    }
} 