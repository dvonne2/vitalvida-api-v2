<?php

namespace App\Services;

use App\Models\VitalVidaInventory\Product as VitalVidaProduct;
use App\Models\VitalVidaInventory\DeliveryAgent as VitalVidaDeliveryAgent;
use App\Models\VitalVidaInventory\Supplier as VitalVidaSupplier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PredictiveRestockingService
{
    /**
     * Predict when products need restocking using machine learning algorithms
     */
    public function generateRestockingRecommendations($lookAheadDays = 30)
    {
        $products = VitalVidaProduct::with(['supplier'])->get();
        $recommendations = [];

        foreach ($products as $product) {
            $prediction = $this->predictRestockingNeeds($product, $lookAheadDays);
            
            if ($prediction['needs_restocking']) {
                $recommendations[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_code' => $product->code,
                    'current_stock' => $product->stock_level,
                    'predicted_stockout_date' => $prediction['stockout_date'],
                    'days_until_stockout' => $prediction['days_until_stockout'],
                    'recommended_order_quantity' => $prediction['recommended_quantity'],
                    'recommended_order_date' => $prediction['recommended_order_date'],
                    'confidence_level' => $prediction['confidence'],
                    'supplier_info' => [
                        'name' => $product->supplier?->company_name,
                        'lead_time' => $product->supplier?->delivery_time ?? '5-7 Days',
                        'minimum_order' => $this->getSupplierMinimumOrder($product->supplier)
                    ],
                    'cost_analysis' => $this->calculateRestockingCosts($product, $prediction['recommended_quantity']),
                    'risk_factors' => $this->identifyRestockingRisks($product),
                    'demand_trend' => $prediction['demand_trend']
                ];
            }
        }

        // Sort by urgency (soonest stockout first)
        usort($recommendations, function($a, $b) {
            return $a['days_until_stockout'] <=> $b['days_until_stockout'];
        });

        return [
            'total_recommendations' => count($recommendations),
            'critical_items' => count(array_filter($recommendations, fn($r) => $r['days_until_stockout'] <= 7)),
            'recommendations' => $recommendations,
            'generated_at' => now(),
            'next_review_date' => now()->addDays(7)
        ];
    }

    private function predictRestockingNeeds($product, $lookAheadDays)
    {
        // Get historical consumption data
        $consumptionHistory = $this->getConsumptionHistory($product, 90); // Last 90 days
        
        // Calculate demand patterns
        $demandAnalysis = $this->analyzeDemandPatterns($consumptionHistory);
        
        // Predict future consumption
        $predictedConsumption = $this->predictFutureConsumption($demandAnalysis, $lookAheadDays);
        
        // Calculate when stock will run out
        $stockoutPrediction = $this->calculateStockoutDate($product->stock_level, $predictedConsumption);
        
        // Calculate optimal reorder point and quantity
        $reorderCalculation = $this->calculateOptimalReorder($product, $demandAnalysis);
        
        return [
            'needs_restocking' => $stockoutPrediction['days_until_stockout'] <= $lookAheadDays,
            'stockout_date' => $stockoutPrediction['stockout_date'],
            'days_until_stockout' => $stockoutPrediction['days_until_stockout'],
            'recommended_quantity' => $reorderCalculation['quantity'],
            'recommended_order_date' => $reorderCalculation['order_date'],
            'confidence' => $stockoutPrediction['confidence'],
            'demand_trend' => $demandAnalysis['trend'],
            'seasonal_factor' => $demandAnalysis['seasonal_factor']
        ];
    }

    private function getConsumptionHistory($product, $days)
    {
        // Get daily consumption from allocations
        $consumptionData = DB::select("
            SELECT 
                DATE(allocated_at) as date,
                SUM(quantity) as daily_consumption
            FROM vitalvida_stock_allocations 
            WHERE product_id = ? 
            AND allocated_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(allocated_at)
            ORDER BY date
        ", [$product->id, $days]);

        return collect($consumptionData)->mapWithKeys(function($item) {
            return [$item->date => $item->daily_consumption];
        })->toArray();
    }

    private function analyzeDemandPatterns($consumptionHistory)
    {
        if (empty($consumptionHistory)) {
            return [
                'average_daily_demand' => 0,
                'trend' => 'stable',
                'volatility' => 0,
                'seasonal_factor' => 1,
                'confidence' => 0
            ];
        }

        $values = array_values($consumptionHistory);
        $days = array_keys($consumptionHistory);
        
        // Calculate basic statistics
        $avgDemand = array_sum($values) / count($values);
        $variance = $this->calculateVariance($values, $avgDemand);
        $volatility = $avgDemand > 0 ? sqrt($variance) / $avgDemand : 0;
        
        // Calculate trend using linear regression
        $trend = $this->calculateTrend($days, $values);
        
        // Calculate seasonal patterns (day of week effect)
        $seasonalFactor = $this->calculateSeasonalFactor($days, $values);
        
        // Calculate confidence based on data quality
        $confidence = $this->calculatePredictionConfidence(count($values), $volatility);
        
        return [
            'average_daily_demand' => round($avgDemand, 2),
            'trend' => $this->interpretTrend($trend),
            'volatility' => round($volatility, 2),
            'seasonal_factor' => $seasonalFactor,
            'confidence' => round($confidence, 2)
        ];
    }

    private function predictFutureConsumption($demandAnalysis, $days)
    {
        $baseDemand = $demandAnalysis['average_daily_demand'];
        $trendFactor = $this->getTrendFactor($demandAnalysis['trend']);
        $seasonalFactor = $demandAnalysis['seasonal_factor'];
        
        $predictions = [];
        for ($day = 1; $day <= $days; $day++) {
            $dayOfWeek = (date('N') + $day - 1) % 7; // 0 = Monday, 6 = Sunday
            $weeklySeasonality = $this->getWeeklySeasonality($dayOfWeek);
            
            $predictedDemand = $baseDemand * $trendFactor * $seasonalFactor * $weeklySeasonality;
            $predictions[$day] = max(0, round($predictedDemand));
        }
        
        return $predictions;
    }

    private function calculateStockoutDate($currentStock, $predictedConsumption)
    {
        $remainingStock = $currentStock;
        $stockoutDay = null;
        
        foreach ($predictedConsumption as $day => $consumption) {
            $remainingStock -= $consumption;
            
            if ($remainingStock <= 0) {
                $stockoutDay = $day;
                break;
            }
        }
        
        if ($stockoutDay === null) {
            // Stock won't run out in prediction period
            $stockoutDay = count($predictedConsumption) + 1;
        }
        
        $stockoutDate = now()->addDays($stockoutDay);
        $confidence = $this->calculateStockoutConfidence($predictedConsumption, $currentStock);
        
        return [
            'stockout_date' => $stockoutDate,
            'days_until_stockout' => $stockoutDay,
            'confidence' => $confidence
        ];
    }

    private function calculateOptimalReorder($product, $demandAnalysis)
    {
        $supplier = $product->supplier;
        $leadTimeDays = $this->parseLeadTime($supplier?->delivery_time ?? '5-7 Days');
        $avgDailyDemand = $demandAnalysis['average_daily_demand'];
        $volatility = $demandAnalysis['volatility'];
        
        // Calculate safety stock (buffer for uncertainty)
        $safetyStock = $avgDailyDemand * $leadTimeDays * (1 + $volatility);
        
        // Calculate reorder point
        $reorderPoint = ($avgDailyDemand * $leadTimeDays) + $safetyStock;
        
        // Calculate Economic Order Quantity (EOQ)
        $annualDemand = $avgDailyDemand * 365;
        $orderCost = 100; // Estimated order processing cost
        $holdingCostRate = 0.2; // 20% annual holding cost
        $unitCost = $product->unit_price ?? 100;
        $holdingCost = $unitCost * $holdingCostRate;
        
        $eoq = $holdingCost > 0 ? sqrt((2 * $annualDemand * $orderCost) / $holdingCost) : 100;
        
        // Adjust for supplier minimum order
        $supplierMin = $this->getSupplierMinimumOrder($supplier);
        $recommendedQuantity = max($eoq, $supplierMin);
        
        // Calculate when to order (considering lead time)
        $currentStock = $product->stock_level;
        $daysUntilReorderPoint = $avgDailyDemand > 0 ? max(0, ($currentStock - $reorderPoint) / $avgDailyDemand) : 0;
        $orderDate = now()->addDays($daysUntilReorderPoint);
        
        return [
            'quantity' => round($recommendedQuantity),
            'order_date' => $orderDate,
            'reorder_point' => round($reorderPoint),
            'safety_stock' => round($safetyStock),
            'eoq' => round($eoq)
        ];
    }

    /**
     * Automated purchase order generation
     */
    public function generateAutomaticPurchaseOrders()
    {
        $recommendations = $this->generateRestockingRecommendations(14); // 2 weeks ahead
        $urgentItems = array_filter($recommendations['recommendations'], function($item) {
            return $item['days_until_stockout'] <= 7; // Critical items
        });
        
        $generatedOrders = [];
        
        foreach ($urgentItems as $item) {
            $product = VitalVidaProduct::find($item['product_id']);
            $supplier = $product->supplier;
            
            if ($supplier && $this->canAutoOrderFromSupplier($supplier)) {
                $purchaseOrder = $this->createAutomaticPurchaseOrder($product, $item);
                $generatedOrders[] = $purchaseOrder;
                
                // Notify relevant staff
                $this->notifyAutomaticOrderGenerated($purchaseOrder);
            }
        }
        
        return [
            'orders_generated' => count($generatedOrders),
            'total_value' => array_sum(array_column($generatedOrders, 'total_amount')),
            'orders' => $generatedOrders,
            'generated_at' => now()
        ];
    }

    private function createAutomaticPurchaseOrder($product, $recommendation)
    {
        $supplier = $product->supplier;
        $quantity = $recommendation['recommended_order_quantity'];
        $unitPrice = $product->cost_price ?? $product->unit_price;
        $totalAmount = $quantity * $unitPrice;
        
        $purchaseOrder = DB::table('vitalvida_purchase_orders')->insertGetId([
            'po_number' => $this->generatePONumber(),
            'supplier_id' => $supplier->id,
            'items' => json_encode([
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $quantity * $unitPrice
                ]
            ]),
            'total_amount' => $totalAmount,
            'order_date' => now(),
            'expected_delivery_date' => $this->calculateExpectedDelivery($supplier),
            'status' => 'Auto-Generated',
            'notes' => "Automatically generated based on predictive restocking analysis. Predicted stockout: {$recommendation['stockout_date']->format('Y-m-d')}",
            'generated_by' => 'system',
            'priority' => $recommendation['days_until_stockout'] <= 3 ? 'urgent' : 'normal',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        return [
            'id' => $purchaseOrder,
            'po_number' => $this->generatePONumber(),
            'supplier_name' => $supplier->company_name,
            'total_amount' => $totalAmount,
            'items_count' => 1,
            'priority' => $recommendation['days_until_stockout'] <= 3 ? 'urgent' : 'normal'
        ];
    }

    // Helper methods
    private function calculateVariance($values, $mean)
    {
        if (count($values) <= 1) return 0;
        
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        return $variance / count($values);
    }

    private function calculateTrend($dates, $values)
    {
        $n = count($values);
        if ($n < 2) return 0;
        
        $x = range(1, $n);
        $y = $values;
        
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumXX = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumXX += $x[$i] * $x[$i];
        }
        
        $denominator = ($n * $sumXX - $sumX * $sumX);
        if ($denominator == 0) return 0;
        
        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        return $slope;
    }

    private function interpretTrend($slope)
    {
        if ($slope > 0.1) return 'increasing';
        if ($slope < -0.1) return 'decreasing';
        return 'stable';
    }

    private function getTrendFactor($trend)
    {
        switch ($trend) {
            case 'increasing': return 1.1;
            case 'decreasing': return 0.9;
            default: return 1.0;
        }
    }

    private function getWeeklySeasonality($dayOfWeek)
    {
        // Business days typically have higher demand
        $weeklyPattern = [
            0 => 1.2, // Monday
            1 => 1.1, // Tuesday
            2 => 1.1, // Wednesday
            3 => 1.1, // Thursday
            4 => 1.0, // Friday
            5 => 0.8, // Saturday
            6 => 0.7  // Sunday
        ];
        
        return $weeklyPattern[$dayOfWeek] ?? 1.0;
    }

    private function parseLeadTime($leadTimeString)
    {
        // Parse "5-7 Days" format
        preg_match('/(\d+)/', $leadTimeString, $matches);
        return isset($matches[1]) ? (int)$matches[1] : 7; // Default 7 days
    }

    private function getSupplierMinimumOrder($supplier)
    {
        return $supplier?->minimum_order_quantity ?? 50; // Default minimum
    }

    private function canAutoOrderFromSupplier($supplier)
    {
        // Check if supplier is approved for automatic ordering
        return $supplier && 
               $supplier->status === 'Active' && 
               ($supplier->rating ?? 0) >= 4.0 && 
               ($supplier->auto_order_enabled ?? false) === true;
    }

    private function generatePONumber()
    {
        $date = now()->format('Ymd');
        $sequence = DB::table('vitalvida_purchase_orders')->whereDate('created_at', today())->count() + 1;
        return "AUTO-PO-{$date}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    private function calculateExpectedDelivery($supplier)
    {
        $leadDays = $this->parseLeadTime($supplier->delivery_time ?? '5-7 Days');
        return now()->addDays($leadDays);
    }

    private function notifyAutomaticOrderGenerated($purchaseOrder)
    {
        Log::info("Automatic purchase order generated", [
            'po_number' => $purchaseOrder['po_number'],
            'supplier' => $purchaseOrder['supplier_name'],
            'total_amount' => $purchaseOrder['total_amount'],
            'priority' => $purchaseOrder['priority']
        ]);
    }

    private function calculateSeasonalFactor($days, $values)
    {
        // Simple seasonal factor calculation
        return 1.0; // Placeholder for more complex seasonal analysis
    }

    private function calculatePredictionConfidence($dataPoints, $volatility)
    {
        // Base confidence on data quality
        $dataQuality = min(1, $dataPoints / 30); // 30 days for good confidence
        $volatilityPenalty = min(0.5, $volatility); // High volatility reduces confidence
        
        return max(0.1, $dataQuality - $volatilityPenalty);
    }

    private function calculateStockoutConfidence($predictions, $currentStock)
    {
        // Calculate confidence based on prediction stability
        $totalDemand = array_sum($predictions);
        $avgDemand = $totalDemand / count($predictions);
        
        // Calculate variance in predictions
        $variance = 0;
        foreach ($predictions as $demand) {
            $variance += pow($demand - $avgDemand, 2);
        }
        $variance /= count($predictions);
        
        $volatility = $avgDemand > 0 ? sqrt($variance) / $avgDemand : 0;
        
        return max(0.5, 1 - $volatility); // Higher volatility = lower confidence
    }

    private function calculateRestockingCosts($product, $quantity)
    {
        $unitCost = $product->cost_price ?? $product->unit_price ?? 100;
        $orderCost = 100; // Fixed order processing cost
        $holdingCostRate = 0.2; // 20% annual holding cost
        
        return [
            'product_cost' => $quantity * $unitCost,
            'order_cost' => $orderCost,
            'annual_holding_cost' => $quantity * $unitCost * $holdingCostRate,
            'total_cost' => ($quantity * $unitCost) + $orderCost
        ];
    }

    private function identifyRestockingRisks($product)
    {
        $risks = [];
        
        // Supplier reliability risk
        $supplier = $product->supplier;
        if ($supplier && ($supplier->rating ?? 0) < 4.0) {
            $risks[] = [
                'type' => 'supplier_reliability',
                'severity' => 'medium',
                'description' => 'Supplier has below average rating'
            ];
        }
        
        // Lead time risk
        $leadTime = $this->parseLeadTime($supplier?->delivery_time ?? '5-7 Days');
        if ($leadTime > 10) {
            $risks[] = [
                'type' => 'long_lead_time',
                'severity' => 'high',
                'description' => "Long supplier lead time ({$leadTime} days)"
            ];
        }
        
        // Demand volatility risk
        $consumptionHistory = $this->getConsumptionHistory($product, 30);
        $demandAnalysis = $this->analyzeDemandPatterns($consumptionHistory);
        
        if ($demandAnalysis['volatility'] > 0.5) {
            $risks[] = [
                'type' => 'demand_volatility',
                'severity' => 'medium',
                'description' => 'High demand volatility makes prediction uncertain'
            ];
        }
        
        return $risks;
    }
}
