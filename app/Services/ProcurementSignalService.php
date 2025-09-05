<?php

namespace App\Services;

use App\Models\VitalVidaProduct;
use App\Models\DeliveryAgent;
use App\Models\VitalVidaSupplier;
use App\Services\PredictiveRestockingService;
use App\Services\RealTimeSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcurementSignalService
{
    protected $predictiveRestockingService;
    protected $realTimeSyncService;

    public function __construct(
        PredictiveRestockingService $predictiveRestockingService,
        RealTimeSyncService $realTimeSyncService
    ) {
        $this->predictiveRestockingService = $predictiveRestockingService;
        $this->realTimeSyncService = $realTimeSyncService;
    }

    /**
     * Generate comprehensive procurement signals
     */
    public function generateProcurementSignals()
    {
        $signals = [
            'reorder_signals' => $this->generateReorderSignals(),
            'stockout_alerts' => $this->generateStockoutAlerts(),
            'demand_surge_signals' => $this->generateDemandSurgeSignals(),
            'cost_optimization_signals' => $this->generateCostOptimizationSignals(),
            'supplier_performance_signals' => $this->generateSupplierPerformanceSignals(),
            'seasonal_preparation_signals' => $this->generateSeasonalPreparationSignals(),
            'emergency_procurement_signals' => $this->generateEmergencyProcurementSignals(),
            'bulk_discount_opportunities' => $this->generateBulkDiscountOpportunities()
        ];

        // Cache signals for performance
        Cache::put('procurement_signals', $signals, now()->addMinutes(30));

        Log::info('Procurement signals generated', [
            'total_signals' => collect($signals)->sum(function($category) {
                return is_array($category) ? count($category) : 0;
            })
        ]);

        return $signals;
    }

    /**
     * Generate reorder signals based on predictive analytics
     */
    private function generateReorderSignals()
    {
        $signals = [];
        $products = VitalVidaProduct::with(['suppliers', 'deliveryAgentProducts'])->get();

        foreach ($products as $product) {
            $currentStock = $this->getCurrentTotalStock($product);
            $reorderPoint = $this->predictiveRestockingService->calculateReorderPoint($product->id);
            $demandForecast = $this->predictiveRestockingService->forecastDemand($product->id, 30);

            if ($currentStock <= $reorderPoint) {
                $urgency = $this->calculateUrgency($currentStock, $reorderPoint, $demandForecast);
                $recommendedQuantity = $this->predictiveRestockingService->calculateOptimalOrderQuantity($product->id);

                $signals[] = [
                    'signal_type' => 'reorder_required',
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'current_stock' => $currentStock,
                    'reorder_point' => $reorderPoint,
                    'recommended_quantity' => $recommendedQuantity,
                    'urgency_level' => $urgency,
                    'estimated_stockout_date' => $this->estimateStockoutDate($product, $currentStock, $demandForecast),
                    'preferred_supplier' => $this->getPreferredSupplier($product),
                    'estimated_cost' => $recommendedQuantity * $product->unit_price,
                    'lead_time_days' => $this->getAverageLeadTime($product),
                    'signal_strength' => $this->calculateSignalStrength($urgency, $currentStock, $reorderPoint),
                    'generated_at' => now()
                ];
            }
        }

        return collect($signals)->sortByDesc('signal_strength')->values()->toArray();
    }

    /**
     * Generate stockout alerts
     */
    private function generateStockoutAlerts()
    {
        $alerts = [];
        $products = VitalVidaProduct::with('deliveryAgentProducts')->get();

        foreach ($products as $product) {
            $currentStock = $this->getCurrentTotalStock($product);
            $dailyConsumption = $this->getDailyConsumption($product);
            $daysUntilStockout = $dailyConsumption > 0 ? $currentStock / $dailyConsumption : 999;

            if ($daysUntilStockout <= 7) { // Alert if stockout within 7 days
                $alerts[] = [
                    'alert_type' => 'imminent_stockout',
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'current_stock' => $currentStock,
                    'daily_consumption' => $dailyConsumption,
                    'days_until_stockout' => round($daysUntilStockout, 1),
                    'risk_level' => $this->getStockoutRiskLevel($daysUntilStockout),
                    'affected_agents' => $this->getAffectedAgents($product),
                    'recommended_action' => $this->getRecommendedStockoutAction($daysUntilStockout),
                    'emergency_suppliers' => $this->getEmergencySuppliers($product),
                    'generated_at' => now()
                ];
            }
        }

        return collect($alerts)->sortBy('days_until_stockout')->values()->toArray();
    }

    /**
     * Generate demand surge signals
     */
    private function generateDemandSurgeSignals()
    {
        $signals = [];
        $products = VitalVidaProduct::all();

        foreach ($products as $product) {
            $currentDemand = $this->getCurrentWeekDemand($product);
            $historicalAverage = $this->getHistoricalAverageDemand($product);
            $surgePercentage = $historicalAverage > 0 ? (($currentDemand - $historicalAverage) / $historicalAverage) * 100 : 0;

            if ($surgePercentage >= 25) { // 25% increase threshold
                $signals[] = [
                    'signal_type' => 'demand_surge',
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'current_demand' => $currentDemand,
                    'historical_average' => $historicalAverage,
                    'surge_percentage' => round($surgePercentage, 1),
                    'trend_direction' => $this->getTrendDirection($product),
                    'recommended_stock_increase' => $this->calculateStockIncrease($surgePercentage),
                    'market_factors' => $this->identifyMarketFactors($product),
                    'procurement_urgency' => $this->calculateProcurementUrgency($surgePercentage),
                    'generated_at' => now()
                ];
            }
        }

        return collect($signals)->sortByDesc('surge_percentage')->values()->toArray();
    }

    /**
     * Generate cost optimization signals
     */
    private function generateCostOptimizationSignals()
    {
        $signals = [];
        $products = VitalVidaProduct::with('suppliers')->get();

        foreach ($products as $product) {
            $suppliers = $product->suppliers;
            if ($suppliers->count() > 1) {
                $costAnalysis = $this->analyzeCostOptimization($product, $suppliers);
                
                if ($costAnalysis['potential_savings'] > 0) {
                    $signals[] = [
                        'signal_type' => 'cost_optimization',
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'current_supplier' => $costAnalysis['current_supplier'],
                        'recommended_supplier' => $costAnalysis['recommended_supplier'],
                        'current_cost' => $costAnalysis['current_cost'],
                        'optimized_cost' => $costAnalysis['optimized_cost'],
                        'potential_savings' => $costAnalysis['potential_savings'],
                        'savings_percentage' => $costAnalysis['savings_percentage'],
                        'quality_impact' => $costAnalysis['quality_impact'],
                        'delivery_impact' => $costAnalysis['delivery_impact'],
                        'risk_assessment' => $costAnalysis['risk_assessment'],
                        'generated_at' => now()
                    ];
                }
            }
        }

        return collect($signals)->sortByDesc('potential_savings')->values()->toArray();
    }

    /**
     * Generate supplier performance signals
     */
    private function generateSupplierPerformanceSignals()
    {
        $signals = [];
        $suppliers = VitalVidaSupplier::with(['products', 'performance'])->get();

        foreach ($suppliers as $supplier) {
            $performance = $supplier->performance;
            if ($performance) {
                $overallRating = $performance->overall_rating;
                
                if ($overallRating < 3.0) { // Poor performance threshold
                    $signals[] = [
                        'signal_type' => 'supplier_performance_issue',
                        'supplier_id' => $supplier->id,
                        'supplier_name' => $supplier->name,
                        'overall_rating' => $overallRating,
                        'delivery_rating' => $performance->delivery_rating,
                        'quality_rating' => $performance->quality_rating,
                        'service_rating' => $performance->service_rating,
                        'affected_products' => $supplier->products->pluck('name')->toArray(),
                        'recommended_action' => $this->getSupplierAction($overallRating),
                        'alternative_suppliers' => $this->getAlternativeSuppliers($supplier),
                        'risk_level' => $this->getSupplierRiskLevel($overallRating),
                        'generated_at' => now()
                    ];
                }
            }
        }

        return collect($signals)->sortBy('overall_rating')->values()->toArray();
    }

    /**
     * Generate seasonal preparation signals
     */
    private function generateSeasonalPreparationSignals()
    {
        $signals = [];
        $seasonalProducts = $this->getSeasonalProducts();

        foreach ($seasonalProducts as $productData) {
            $product = $productData['product'];
            $seasonalFactor = $productData['seasonal_factor'];
            $daysUntilSeason = $productData['days_until_season'];

            if ($daysUntilSeason <= 60 && $daysUntilSeason > 0) { // 2 months preparation window
                $currentStock = $this->getCurrentTotalStock($product);
                $seasonalDemand = $this->getSeasonalDemand($product, $seasonalFactor);
                $requiredStock = $seasonalDemand - $currentStock;

                if ($requiredStock > 0) {
                    $signals[] = [
                        'signal_type' => 'seasonal_preparation',
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'season_name' => $productData['season_name'],
                        'seasonal_factor' => $seasonalFactor,
                        'days_until_season' => $daysUntilSeason,
                        'current_stock' => $currentStock,
                        'projected_seasonal_demand' => $seasonalDemand,
                        'additional_stock_needed' => $requiredStock,
                        'procurement_deadline' => now()->addDays($daysUntilSeason - 30),
                        'estimated_procurement_cost' => $requiredStock * $product->unit_price,
                        'preparation_urgency' => $this->calculatePreparationUrgency($daysUntilSeason),
                        'generated_at' => now()
                    ];
                }
            }
        }

        return collect($signals)->sortBy('days_until_season')->values()->toArray();
    }

    /**
     * Generate emergency procurement signals
     */
    private function generateEmergencyProcurementSignals()
    {
        $signals = [];
        $criticalProducts = $this->getCriticalProducts();

        foreach ($criticalProducts as $product) {
            $currentStock = $this->getCurrentTotalStock($product);
            $safetyStock = $this->getSafetyStock($product);
            $emergencyThreshold = $safetyStock * 0.5; // 50% of safety stock

            if ($currentStock <= $emergencyThreshold) {
                $signals[] = [
                    'signal_type' => 'emergency_procurement',
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'current_stock' => $currentStock,
                    'safety_stock' => $safetyStock,
                    'emergency_threshold' => $emergencyThreshold,
                    'criticality_level' => $this->getCriticalityLevel($product),
                    'immediate_quantity_needed' => $safetyStock - $currentStock,
                    'emergency_suppliers' => $this->getEmergencySuppliers($product),
                    'expedited_delivery_options' => $this->getExpeditedDeliveryOptions($product),
                    'estimated_emergency_cost' => $this->calculateEmergencyCost($product, $safetyStock - $currentStock),
                    'business_impact' => $this->assessBusinessImpact($product),
                    'generated_at' => now()
                ];
            }
        }

        return collect($signals)->sortByDesc('criticality_level')->values()->toArray();
    }

    /**
     * Generate bulk discount opportunities
     */
    private function generateBulkDiscountOpportunities()
    {
        $opportunities = [];
        $products = VitalVidaProduct::with('suppliers')->get();

        foreach ($products as $product) {
            $currentOrderQuantity = $this->predictiveRestockingService->calculateOptimalOrderQuantity($product->id);
            $bulkTiers = $this->getBulkDiscountTiers($product);

            foreach ($bulkTiers as $tier) {
                if ($currentOrderQuantity < $tier['min_quantity']) {
                    $additionalQuantity = $tier['min_quantity'] - $currentOrderQuantity;
                    $currentCost = $currentOrderQuantity * $product->unit_price;
                    $bulkCost = $tier['min_quantity'] * $tier['discounted_price'];
                    $savings = $currentCost - $bulkCost;

                    if ($savings > 0) {
                        $opportunities[] = [
                            'opportunity_type' => 'bulk_discount',
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'current_order_quantity' => $currentOrderQuantity,
                            'bulk_tier_quantity' => $tier['min_quantity'],
                            'additional_quantity_needed' => $additionalQuantity,
                            'current_unit_price' => $product->unit_price,
                            'discounted_unit_price' => $tier['discounted_price'],
                            'current_total_cost' => $currentCost,
                            'bulk_total_cost' => $bulkCost,
                            'potential_savings' => $savings,
                            'discount_percentage' => $tier['discount_percentage'],
                            'storage_capacity_check' => $this->checkStorageCapacity($product, $additionalQuantity),
                            'cash_flow_impact' => $this->assessCashFlowImpact($bulkCost - $currentCost),
                            'roi_timeline' => $this->calculateROITimeline($product, $additionalQuantity),
                            'generated_at' => now()
                        ];
                    }
                }
            }
        }

        return collect($opportunities)->sortByDesc('potential_savings')->values()->toArray();
    }

    /**
     * Get procurement signal analytics
     */
    public function getProcurementSignalAnalytics()
    {
        $signals = Cache::get('procurement_signals', $this->generateProcurementSignals());

        return [
            'signal_summary' => [
                'total_reorder_signals' => count($signals['reorder_signals']),
                'total_stockout_alerts' => count($signals['stockout_alerts']),
                'total_demand_surge_signals' => count($signals['demand_surge_signals']),
                'total_cost_optimization_signals' => count($signals['cost_optimization_signals']),
                'total_supplier_performance_signals' => count($signals['supplier_performance_signals']),
                'total_seasonal_preparation_signals' => count($signals['seasonal_preparation_signals']),
                'total_emergency_procurement_signals' => count($signals['emergency_procurement_signals']),
                'total_bulk_discount_opportunities' => count($signals['bulk_discount_opportunities'])
            ],
            'financial_impact' => [
                'total_procurement_value' => $this->calculateTotalProcurementValue($signals),
                'potential_cost_savings' => $this->calculatePotentialCostSavings($signals),
                'emergency_procurement_cost' => $this->calculateEmergencyProcurementCost($signals),
                'bulk_discount_savings' => $this->calculateBulkDiscountSavings($signals)
            ],
            'urgency_breakdown' => [
                'critical_signals' => $this->countSignalsByUrgency($signals, 'critical'),
                'high_signals' => $this->countSignalsByUrgency($signals, 'high'),
                'medium_signals' => $this->countSignalsByUrgency($signals, 'medium'),
                'low_signals' => $this->countSignalsByUrgency($signals, 'low')
            ],
            'top_priority_actions' => $this->getTopPriorityActions($signals),
            'supplier_recommendations' => $this->getSupplierRecommendations($signals),
            'generated_at' => now()
        ];
    }

    // Helper methods (simplified implementations)
    private function getCurrentTotalStock($product) { return 100; } // Placeholder
    private function getDailyConsumption($product) { return 5; } // Placeholder
    private function getCurrentWeekDemand($product) { return 35; } // Placeholder
    private function getHistoricalAverageDemand($product) { return 25; } // Placeholder
    private function calculateUrgency($currentStock, $reorderPoint, $demandForecast) { return 'high'; }
    private function estimateStockoutDate($product, $currentStock, $demandForecast) { return now()->addDays(7); }
    private function getPreferredSupplier($product) { return $product->suppliers->first(); }
    private function getAverageLeadTime($product) { return 7; }
    private function calculateSignalStrength($urgency, $currentStock, $reorderPoint) { return 85; }
    private function getStockoutRiskLevel($days) { return $days <= 2 ? 'critical' : ($days <= 5 ? 'high' : 'medium'); }
    private function getAffectedAgents($product) { return ['Agent A', 'Agent B']; }
    private function getRecommendedStockoutAction($days) { return $days <= 2 ? 'emergency_order' : 'expedite_order'; }
    private function getEmergencySuppliers($product) { return $product->suppliers->take(2); }
    private function getTrendDirection($product) { return 'increasing'; }
    private function calculateStockIncrease($surgePercentage) { return round($surgePercentage * 0.8); }
    private function identifyMarketFactors($product) { return ['seasonal_demand', 'market_expansion']; }
    private function calculateProcurementUrgency($surgePercentage) { return $surgePercentage > 50 ? 'critical' : 'high'; }
    private function analyzeCostOptimization($product, $suppliers) { 
        return [
            'current_supplier' => $suppliers->first(),
            'recommended_supplier' => $suppliers->last(),
            'current_cost' => 1000,
            'optimized_cost' => 850,
            'potential_savings' => 150,
            'savings_percentage' => 15,
            'quality_impact' => 'minimal',
            'delivery_impact' => 'none',
            'risk_assessment' => 'low'
        ];
    }
    private function getSupplierAction($rating) { return $rating < 2 ? 'replace' : 'improve'; }
    private function getAlternativeSuppliers($supplier) { return ['Alternative Supplier A', 'Alternative Supplier B']; }
    private function getSupplierRiskLevel($rating) { return $rating < 2 ? 'critical' : 'high'; }
    private function getSeasonalProducts() { return []; } // Would return actual seasonal product data
    private function getSeasonalDemand($product, $factor) { return 200; }
    private function calculatePreparationUrgency($days) { return $days <= 30 ? 'critical' : 'medium'; }
    private function getCriticalProducts() { return VitalVidaProduct::take(5)->get(); }
    private function getSafetyStock($product) { return 50; }
    private function getCriticalityLevel($product) { return 'high'; }
    private function getExpeditedDeliveryOptions($product) { return ['same_day', 'next_day']; }
    private function calculateEmergencyCost($product, $quantity) { return $quantity * $product->unit_price * 1.5; }
    private function assessBusinessImpact($product) { return 'high_revenue_impact'; }
    private function getBulkDiscountTiers($product) { 
        return [
            ['min_quantity' => 500, 'discounted_price' => $product->unit_price * 0.9, 'discount_percentage' => 10],
            ['min_quantity' => 1000, 'discounted_price' => $product->unit_price * 0.85, 'discount_percentage' => 15]
        ];
    }
    private function checkStorageCapacity($product, $quantity) { return 'sufficient'; }
    private function assessCashFlowImpact($additionalCost) { return $additionalCost > 100000 ? 'significant' : 'manageable'; }
    private function calculateROITimeline($product, $quantity) { return '3_months'; }
    private function calculateTotalProcurementValue($signals) { return 500000; }
    private function calculatePotentialCostSavings($signals) { return 75000; }
    private function calculateEmergencyProcurementCost($signals) { return 25000; }
    private function calculateBulkDiscountSavings($signals) { return 15000; }
    private function countSignalsByUrgency($signals, $urgency) { return 5; }
    private function getTopPriorityActions($signals) { return ['Reorder Product A', 'Emergency order Product B']; }
    private function getSupplierRecommendations($signals) { return ['Switch to Supplier X for Product A']; }
}
