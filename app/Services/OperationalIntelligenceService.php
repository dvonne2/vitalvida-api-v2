<?php

namespace App\Services;

use App\Models\Order;
use App\Models\DeliveryAgent;
use App\Models\Department;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OperationalIntelligenceService
{
    /**
     * Calculate process efficiency for a department
     */
    public function calculateProcessEfficiency($department)
    {
        $efficiencyMetrics = [];

        switch ($department) {
            case 'telesales':
                $efficiencyMetrics = $this->getTelesalesEfficiency();
                break;
            case 'logistics':
                $efficiencyMetrics = $this->getLogisticsEfficiency();
                break;
            case 'production':
                $efficiencyMetrics = $this->getProductionEfficiency();
                break;
            case 'quality_control':
                $efficiencyMetrics = $this->getQualityControlEfficiency();
                break;
            default:
                $efficiencyMetrics = $this->getOverallEfficiency();
        }

        return [
            'department' => $department,
            'efficiency_score' => $efficiencyMetrics['score'],
            'metrics' => $efficiencyMetrics['metrics'],
            'benchmarks' => $efficiencyMetrics['benchmarks'],
            'improvement_opportunities' => $efficiencyMetrics['opportunities'],
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Track automation score
     */
    public function trackAutomationScore()
    {
        $automationAreas = [
            'financial_operations' => [
                'automated_processes' => ['Payment reconciliation', 'Refund tracking', 'Invoice generation'],
                'manual_processes' => ['Vendor payment approvals'],
                'automation_level' => 87,
                'target' => 95
            ],
            'inventory_management' => [
                'automated_processes' => ['Stock alerts', 'Reorder points', 'Batch tracking'],
                'manual_processes' => ['Physical stock counts'],
                'automation_level' => 73,
                'target' => 90
            ],
            'delivery_coordination' => [
                'automated_processes' => ['DA assignment', 'Route optimization', 'Tracking'],
                'manual_processes' => ['DA performance reviews'],
                'automation_level' => 91,
                'target' => 95
            ],
            'quality_control' => [
                'automated_processes' => ['Package verification', 'Seal integrity checks'],
                'manual_processes' => ['Final quality inspection'],
                'automation_level' => 85,
                'target' => 90
            ]
        ];

        $overallScore = array_sum(array_column($automationAreas, 'automation_level')) / count($automationAreas);

        return [
            'overall_automation_score' => round($overallScore, 1),
            'target_score' => 90,
            'automation_areas' => $automationAreas,
            'improvement_roadmap' => [
                'Q1_2025' => 'Implement RFID inventory tracking',
                'Q2_2025' => 'Deploy AI route optimization',
                'Q3_2025' => 'Computer vision quality control',
                'Q4_2025' => 'Fully automated vendor payments'
            ],
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Compute unit economics for a specific SKU
     */
    public function computeUnitEconomics($sku)
    {
        $unitEconomics = [];

        switch ($sku) {
            case 'moringa_capsules_60ct':
                $unitEconomics = [
                    'selling_price' => 15000,
                    'cost_of_goods' => 850,
                    'gross_profit' => 14150,
                    'gross_margin' => 94.3,
                    'operating_expenses' => 3000,
                    'net_profit' => 11150,
                    'net_margin' => 74.3,
                    'breakdown' => [
                        'bottle_label' => 180,
                        'ingredients' => 420,
                        'packaging' => 85,
                        'delivery' => 165
                    ]
                ];
                break;
            case 'ginger_complex_30ct':
                $unitEconomics = [
                    'selling_price' => 12500,
                    'cost_of_goods' => 620,
                    'gross_profit' => 11880,
                    'gross_margin' => 95.0,
                    'operating_expenses' => 2500,
                    'net_profit' => 9380,
                    'net_margin' => 75.0,
                    'breakdown' => [
                        'bottle_label' => 150,
                        'ingredients' => 350,
                        'packaging' => 70,
                        'delivery' => 150
                    ]
                ];
                break;
            case 'turmeric_boost_45ct':
                $unitEconomics = [
                    'selling_price' => 13500,
                    'cost_of_goods' => 720,
                    'gross_profit' => 12780,
                    'gross_margin' => 94.7,
                    'operating_expenses' => 2700,
                    'net_profit' => 10080,
                    'net_margin' => 74.7,
                    'breakdown' => [
                        'bottle_label' => 160,
                        'ingredients' => 380,
                        'packaging' => 80,
                        'delivery' => 160
                    ]
                ];
                break;
        }

        return [
            'sku' => $sku,
            'unit_economics' => $unitEconomics,
            'profitability_analysis' => [
                'most_profitable' => $unitEconomics['net_margin'] > 75,
                'margin_trend' => 'stable',
                'optimization_opportunities' => $this->getOptimizationOpportunities($sku)
            ],
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Analyze wastage patterns
     */
    public function analyzeWastagePatterns()
    {
        $wastageData = [
            'broken_bottles' => [
                'units' => 8,
                'cost_impact' => 1440,
                'percentage' => 3.2,
                'trend' => 'decreasing',
                'root_cause' => 'Transport damage'
            ],
            'failed_seals' => [
                'units' => 3,
                'cost_impact' => 540,
                'percentage' => 1.2,
                'trend' => 'stable',
                'root_cause' => 'Sealing machine malfunction'
            ],
            'rejected_cartons' => [
                'units' => 2,
                'cost_impact' => 360,
                'percentage' => 0.8,
                'trend' => 'decreasing',
                'root_cause' => 'DA Returns'
            ],
            'expired_products' => [
                'units' => 0,
                'cost_impact' => 0,
                'percentage' => 0,
                'trend' => 'stable',
                'root_cause' => 'None'
            ]
        ];

        $totalWastage = array_sum(array_column($wastageData, 'cost_impact'));
        $totalWastagePercentage = array_sum(array_column($wastageData, 'percentage'));

        return [
            'wastage_summary' => [
                'total_units_wasted' => array_sum(array_column($wastageData, 'units')),
                'total_cost_impact' => $totalWastage,
                'total_wastage_percentage' => $totalWastagePercentage,
                'overall_trend' => 'improving'
            ],
            'wastage_breakdown' => $wastageData,
            'prevention_strategies' => [
                'broken_bottles' => 'Improved packaging and handling procedures',
                'failed_seals' => 'Regular machine maintenance and calibration',
                'rejected_cartons' => 'Better DA training and quality checks',
                'expired_products' => 'Improved inventory management'
            ],
            'cost_savings_potential' => [
                'immediate_savings' => $totalWastage * 0.3, // 30% reduction potential
                'long_term_savings' => $totalWastage * 0.5, // 50% reduction potential
                'implementation_cost' => 50000,
                'roi_timeline' => '3 months'
            ],
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Generate cost deviation alerts
     */
    public function generateCostDeviationAlerts()
    {
        $alerts = [
            [
                'id' => 1,
                'type' => 'cost_deviation',
                'category' => 'materials',
                'item' => 'Bottle cost',
                'deviation' => '+8%',
                'threshold' => '5%',
                'severity' => 'medium',
                'status' => 'investigating',
                'description' => 'Bottle cost increased by 8% from TopPak Ltd',
                'action_required' => 'Negotiate with vendor or find alternative',
                'created_at' => '2024-12-06 09:15',
                'assigned_to' => 'Dangote Cost Control'
            ],
            [
                'id' => 2,
                'type' => 'yield_deviation',
                'category' => 'production',
                'item' => 'Production yield',
                'deviation' => '-15%',
                'threshold' => '10%',
                'severity' => 'high',
                'status' => 'resolved',
                'description' => 'Yield dropped to 17/20 units (Production issue)',
                'action_required' => 'Investigate production process',
                'created_at' => '2024-12-05 14:30',
                'assigned_to' => 'Andy Tech'
            ],
            [
                'id' => 3,
                'type' => 'cost_improvement',
                'category' => 'logistics',
                'item' => 'Delivery cost',
                'deviation' => '-5%',
                'threshold' => '5%',
                'severity' => 'positive',
                'status' => 'implemented',
                'description' => 'Delivery cost reduced by 5% through route optimization',
                'action_required' => 'Expand route optimization to other areas',
                'created_at' => '2024-12-04 11:45',
                'assigned_to' => 'Otunba Control'
            ]
        ];

        $alertSummary = [
            'total_alerts' => count($alerts),
            'critical_alerts' => count(array_filter($alerts, fn($a) => $a['severity'] === 'high')),
            'medium_alerts' => count(array_filter($alerts, fn($a) => $a['severity'] === 'medium')),
            'positive_alerts' => count(array_filter($alerts, fn($a) => $a['severity'] === 'positive')),
            'resolved_alerts' => count(array_filter($alerts, fn($a) => $a['status'] === 'resolved')),
            'pending_alerts' => count(array_filter($alerts, fn($a) => $a['status'] !== 'resolved'))
        ];

        return [
            'alerts' => $alerts,
            'summary' => $alertSummary,
            'trends' => [
                'alert_frequency' => 'decreasing',
                'resolution_time' => '2.3 days average',
                'cost_impact' => '₦48.5K total impact',
                'savings_potential' => '₦25K monthly savings'
            ],
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Get telesales efficiency
     */
    private function getTelesalesEfficiency()
    {
        return [
            'score' => 92,
            'metrics' => [
                'orders_per_hour' => 3.2,
                'conversion_rate' => '8.5%',
                'average_order_value' => 12500,
                'customer_satisfaction' => '94.5%'
            ],
            'benchmarks' => [
                'industry_average' => 85,
                'target' => 95,
                'previous_period' => 88
            ],
            'opportunities' => [
                'improve_script_effectiveness' => 'Potential 15% conversion increase',
                'enhance_customer_training' => 'Potential 10% satisfaction increase',
                'optimize_call_scheduling' => 'Potential 20% efficiency increase'
            ]
        ];
    }

    /**
     * Get logistics efficiency
     */
    private function getLogisticsEfficiency()
    {
        return [
            'score' => 88,
            'metrics' => [
                'delivery_success_rate' => '98.5%',
                'average_delivery_time' => '2.8 days',
                'route_optimization_score' => '91%',
                'da_performance_rating' => '4.2/5'
            ],
            'benchmarks' => [
                'industry_average' => 82,
                'target' => 90,
                'previous_period' => 85
            ],
            'opportunities' => [
                'implement_ai_routing' => 'Potential 25% delivery time reduction',
                'enhance_da_training' => 'Potential 15% performance improvement',
                'optimize_warehouse_layout' => 'Potential 10% efficiency increase'
            ]
        ];
    }

    /**
     * Get production efficiency
     */
    private function getProductionEfficiency()
    {
        return [
            'score' => 85,
            'metrics' => [
                'units_per_hour' => 8.5,
                'quality_score' => '98.9%',
                'waste_percentage' => '5.6%',
                'equipment_uptime' => '94.2%'
            ],
            'benchmarks' => [
                'industry_average' => 80,
                'target' => 90,
                'previous_period' => 82
            ],
            'opportunities' => [
                'automate_quality_control' => 'Potential 20% quality improvement',
                'optimize_production_schedule' => 'Potential 15% efficiency increase',
                'implement_predictive_maintenance' => 'Potential 10% uptime improvement'
            ]
        ];
    }

    /**
     * Get quality control efficiency
     */
    private function getQualityControlEfficiency()
    {
        return [
            'score' => 94,
            'metrics' => [
                'defect_detection_rate' => '99.2%',
                'false_positive_rate' => '0.8%',
                'inspection_time' => '45 seconds',
                'customer_complaints' => '2.1%'
            ],
            'benchmarks' => [
                'industry_average' => 88,
                'target' => 95,
                'previous_period' => 91
            ],
            'opportunities' => [
                'implement_computer_vision' => 'Potential 30% inspection speed increase',
                'enhance_defect_detection' => 'Potential 15% accuracy improvement',
                'automate_reporting' => 'Potential 25% time savings'
            ]
        ];
    }

    /**
     * Get overall efficiency
     */
    private function getOverallEfficiency()
    {
        return [
            'score' => 90,
            'metrics' => [
                'overall_efficiency' => '90%',
                'cost_per_output' => '₦850',
                'quality_score' => '98.9%',
                'customer_satisfaction' => '94.5%'
            ],
            'benchmarks' => [
                'industry_average' => 85,
                'target' => 92,
                'previous_period' => 87
            ],
            'opportunities' => [
                'cross_department_optimization' => 'Potential 10% overall improvement',
                'technology_integration' => 'Potential 15% efficiency increase',
                'process_standardization' => 'Potential 8% consistency improvement'
            ]
        ];
    }

    /**
     * Get optimization opportunities for SKU
     */
    private function getOptimizationOpportunities($sku)
    {
        $opportunities = [
            'moringa_capsules_60ct' => [
                'bulk_purchasing' => 'Potential 10% cost reduction',
                'automated_packaging' => 'Potential 15% efficiency increase',
                'supplier_negotiation' => 'Potential 5% price reduction'
            ],
            'ginger_complex_30ct' => [
                'ingredient_sourcing' => 'Potential 8% cost reduction',
                'packaging_optimization' => 'Potential 12% efficiency increase',
                'route_optimization' => 'Potential 6% delivery cost reduction'
            ],
            'turmeric_boost_45ct' => [
                'production_automation' => 'Potential 20% efficiency increase',
                'quality_control_enhancement' => 'Potential 15% defect reduction',
                'inventory_optimization' => 'Potential 10% holding cost reduction'
            ]
        ];

        return $opportunities[$sku] ?? [];
    }
} 