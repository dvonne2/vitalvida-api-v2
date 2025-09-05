<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\Order;
use App\Models\Product;
use App\Models\Vendor;
use Carbon\Carbon;

class DangoteCostControlController extends Controller
{
    /**
     * Get Dangote Cost Control dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_DANGOTE_COST_CONTROL) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Dangote Cost Control access required.'
                ], 403);
            }

            $data = [
                'unit_economics_breakdown' => $this->getUnitEconomicsBreakdown(),
                'cost_deviation_alerts' => $this->getCostDeviationAlerts(),
                'recent_deviation_log' => $this->getRecentDeviationLog(),
                'wastage_returns_audit' => $this->getWastageReturnsAudit(),
                'vendor_oversight_procurement' => $this->getVendorOversightProcurement(),
                'departmental_cost_vs_output' => $this->getDepartmentalCostVsOutput(),
                'time_to_revenue_tracking' => $this->getTimeToRevenueTracking(),
                'weekly_control_summary' => $this->getWeeklyControlSummary(),
                'cost_optimization_opportunities' => $this->getCostOptimizationOpportunities(),
                'efficiency_metrics' => $this->getEfficiencyMetrics()
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'investor_role' => $investor->role,
                    'access_level' => $investor->access_level
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load Dangote Cost Control dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unit economics breakdown
     */
    private function getUnitEconomicsBreakdown()
    {
        return [
            'cost_per_unit' => [
                'bottle_label' => 180,
                'bottle_label_formatted' => '₦180',
                'ingredients' => 420,
                'ingredients_formatted' => '₦420',
                'packaging' => 85,
                'packaging_formatted' => '₦85',
                'delivery' => 165,
                'delivery_formatted' => '₦165',
                'total_cost' => 850,
                'total_cost_formatted' => '₦850'
            ],
            'input_output_yield' => [
                'expected_yield' => '20 units/L',
                'actual_yield' => '17 units',
                'yield_shortfall' => '-15% Loss',
                'yield_efficiency' => 85,
                'waste_percentage' => 15
            ],
            'profitability_analysis' => [
                'selling_price' => 15000,
                'selling_price_formatted' => '₦15,000',
                'cost_of_goods' => 850,
                'cost_of_goods_formatted' => '₦850',
                'gross_profit' => 14150,
                'gross_profit_formatted' => '₦14,150',
                'gross_margin' => '94.3%',
                'net_margin' => '51.2%'
            ],
            'cost_trends' => [
                'bottle_cost_trend' => '+8% vs last month',
                'ingredients_cost_trend' => 'stable',
                'packaging_cost_trend' => '-2% vs last month',
                'delivery_cost_trend' => '-5% vs last month'
            ]
        ];
    }

    /**
     * Get cost deviation alerts
     */
    private function getCostDeviationAlerts()
    {
        return [
            'bottle_cost' => [
                'status' => 'active',
                'deviation' => '+8%',
                'note' => 'Vendor price increase',
                'severity' => 'medium',
                'action_required' => 'Negotiate with vendor or find alternative'
            ],
            'ingredients_cost' => [
                'status' => 'normal',
                'note' => 'Within baseline',
                'deviation' => '0%',
                'severity' => 'none',
                'action_required' => 'None'
            ],
            'packaging_cost' => [
                'status' => 'improving',
                'deviation' => '-2%',
                'note' => 'Bulk purchase discount',
                'severity' => 'positive',
                'action_required' => 'Continue bulk purchasing'
            ],
            'delivery_cost' => [
                'status' => 'improving',
                'deviation' => '-5%',
                'note' => 'Route optimization savings',
                'severity' => 'positive',
                'action_required' => 'Expand route optimization'
            ]
        ];
    }

    /**
     * Get recent deviation log
     */
    private function getRecentDeviationLog()
    {
        return [
            [
                'date' => '2024-12-06 09:15',
                'item' => 'Bottle cost +8% (Vendor: TopPak Ltd)',
                'category' => 'materials',
                'impact' => 'medium',
                'status' => 'investigating'
            ],
            [
                'date' => '2024-12-05 14:30',
                'item' => 'Yield drop to 17/20 units (Production issue)',
                'category' => 'production',
                'impact' => 'high',
                'status' => 'resolved'
            ],
            [
                'date' => '2024-12-04 11:45',
                'item' => 'Delivery cost reduced -5% (New routes)',
                'category' => 'logistics',
                'impact' => 'positive',
                'status' => 'implemented'
            ],
            [
                'date' => '2024-12-03 16:20',
                'item' => 'Packaging cost -2% (Bulk discount)',
                'category' => 'materials',
                'impact' => 'positive',
                'status' => 'implemented'
            ],
            [
                'date' => '2024-12-02 10:30',
                'item' => 'Ingredients cost stable (Supplier: NatureChem)',
                'category' => 'materials',
                'impact' => 'neutral',
                'status' => 'monitoring'
            ]
        ];
    }

    /**
     * Get wastage returns audit
     */
    private function getWastageReturnsAudit()
    {
        return [
            'wastage_log' => [
                'broken_bottles' => [
                    'units' => 8,
                    'cost_impact' => '3.2%',
                    'cost_impact_amount' => 1440,
                    'reason' => 'Transport damage'
                ],
                'failed_seals' => [
                    'units' => 3,
                    'cost_impact' => 'return_rate',
                    'cost_impact_amount' => 540,
                    'reason' => 'Sealing machine malfunction'
                ],
                'rejected_cartons' => [
                    'units' => 2,
                    'cost_impact' => '1.2%',
                    'cost_impact_amount' => 360,
                    'reason' => 'DA Returns'
                ],
                'total_wastage' => '13 units',
                'total_wastage_cost' => 2340,
                'wastage_percentage' => '5.6%'
            ],
            'returned_goods_monitor' => [
                'da_returns' => [
                    'value' => 25500,
                    'value_formatted' => '₦25.5K',
                    'percentage' => '3.2%',
                    'units' => 17
                ],
                'customer_refunds' => [
                    'value' => 12000,
                    'value_formatted' => '₦12K',
                    'percentage' => '1.5%',
                    'units' => 8
                ],
                'total_returns' => [
                    'value' => 37500,
                    'value_formatted' => '₦37.5K',
                    'percentage' => '4.7%',
                    'units' => 25
                ]
            ],
            'weekly_loss_report' => [
                'total_loss' => 48500,
                'total_loss_formatted' => '₦48.5K',
                'vs_last_week' => '+12%',
                'loss_categories' => [
                    'wastage' => 2340,
                    'returns' => 37500,
                    'damage' => 8660
                ]
            ]
        ];
    }

    /**
     * Get vendor oversight procurement
     */
    private function getVendorOversightProcurement()
    {
        return [
            'vendor_ledger' => [
                [
                    'vendor' => 'TopPak Ltd',
                    'product' => 'Bottles & Labels',
                    'amount_owed' => 100000,
                    'amount_owed_formatted' => '₦100K',
                    'avg_days' => '3 days',
                    'payment_terms' => 'Net 7',
                    'performance_rating' => 'good'
                ],
                [
                    'vendor' => 'NatureChem',
                    'product' => 'Raw Ingredients',
                    'amount_owed' => 420000,
                    'amount_owed_formatted' => '₦420K',
                    'avg_days' => '5 days',
                    'payment_terms' => 'Net 10',
                    'performance_rating' => 'excellent'
                ],
                [
                    'vendor' => 'FastPak',
                    'product' => 'Packaging Materials',
                    'amount_owed' => 75000,
                    'amount_owed_formatted' => '₦75K',
                    'avg_days' => '2 days',
                    'payment_terms' => 'Net 5',
                    'performance_rating' => 'good'
                ]
            ],
            'vendor_flags' => [
                [
                    'vendor' => 'NatureChem',
                    'issue' => 'Delivery delay >2 days',
                    'flag_status' => 'flag',
                    'details' => '2024-12-01 - Added FastPak as backup vendor (Approved: CEO)',
                    'severity' => 'medium'
                ],
                [
                    'vendor' => 'SlowSupply',
                    'issue' => 'Quality issues',
                    'flag_status' => 'removed',
                    'details' => '2024-11-28 - Removed SlowSupply (Quality issues, Approved: CFO)',
                    'severity' => 'high'
                ],
                [
                    'vendor' => 'TopPak Ltd',
                    'issue' => 'Price increase 8%',
                    'flag_status' => 'monitoring',
                    'details' => '2024-12-06 - Price increase effective immediately',
                    'severity' => 'medium'
                ]
            ],
            'procurement_metrics' => [
                'total_vendors' => 8,
                'active_vendors' => 7,
                'flagged_vendors' => 1,
                'removed_vendors' => 1,
                'average_payment_time' => '4.2 days',
                'vendor_satisfaction_score' => '8.7/10'
            ]
        ];
    }

    /**
     * Get departmental cost vs output
     */
    private function getDepartmentalCostVsOutput()
    {
        return [
            'telesales' => [
                'cost_per_output' => 65,
                'cost_per_output_formatted' => '₦65/order',
                'bonus_vs_output_ratio' => '+4,850',
                'revenue_per_bonus' => 'Above target',
                'orders_processed' => 47,
                'total_cost' => 3055,
                'total_cost_formatted' => '₦3,055',
                'efficiency_score' => 92
            ],
            'logistics' => [
                'cost_per_delivery' => 165,
                'cost_per_delivery_formatted' => '₦165',
                'total_deliveries' => 42,
                'revenue_generated' => 625000,
                'revenue_generated_formatted' => '₦625K',
                'efficiency' => 'Above target',
                'total_cost' => 6930,
                'total_cost_formatted' => '₦6,930',
                'efficiency_score' => 88
            ],
            'production' => [
                'cost_per_unit' => 665,
                'cost_per_unit_formatted' => '₦665',
                'units_produced' => 68,
                'total_cost' => 45220,
                'total_cost_formatted' => '₦45,220',
                'efficiency_score' => 85
            ],
            'quality_control' => [
                'cost_per_inspection' => 45,
                'cost_per_inspection_formatted' => '₦45',
                'inspections_completed' => 47,
                'defects_caught' => 3,
                'total_cost' => 2115,
                'total_cost_formatted' => '₦2,115',
                'efficiency_score' => 94
            ]
        ];
    }

    /**
     * Get time to revenue tracking
     */
    private function getTimeToRevenueTracking()
    {
        return [
            'production_to_pack' => '0.5 days',
            'pack_to_delivery' => '2.8 days',
            'delivery_to_payment' => '0.5 days',
            'total_cycle' => '4.2 days',
            'cycle_breakdown' => [
                'order_processing' => '0.1 days',
                'production' => '0.5 days',
                'packaging' => '0.2 days',
                'inventory_pick' => '0.1 days',
                'da_assignment' => '0.1 days',
                'delivery' => '2.8 days',
                'payment_confirmation' => '0.5 days'
            ],
            'optimization_targets' => [
                'reduce_delivery_time' => 'Target: 2.0 days',
                'automate_payment_confirmation' => 'Target: 0.1 days',
                'streamline_production' => 'Target: 0.3 days'
            ],
            'bottlenecks' => [
                'delivery_time' => 'Longest cycle time',
                'payment_confirmation' => 'Manual verification needed',
                'production_scheduling' => 'Batch processing delays'
            ]
        ];
    }

    /**
     * Get weekly control summary
     */
    private function getWeeklyControlSummary()
    {
        return [
            'units_produced' => 68,
            'total_loss' => 48500,
            'total_loss_formatted' => '₦48.5K',
            'loss_percentage' => '5.6%',
            'cost_efficiency_score' => 85,
            'weekly_metrics' => [
                'production_efficiency' => '85%',
                'waste_reduction' => '12%',
                'cost_savings' => '₦15.2K',
                'vendor_optimization' => '2 vendors improved'
            ],
            'trends' => [
                'cost_trend' => 'decreasing',
                'efficiency_trend' => 'improving',
                'waste_trend' => 'decreasing',
                'quality_trend' => 'stable'
            ]
        ];
    }

    /**
     * Get cost optimization opportunities
     */
    private function getCostOptimizationOpportunities()
    {
        return [
            'immediate_opportunities' => [
                [
                    'opportunity' => 'Bulk purchase discount',
                    'potential_savings' => '₦25K/month',
                    'implementation_time' => '1 week',
                    'priority' => 'high'
                ],
                [
                    'opportunity' => 'Route optimization',
                    'potential_savings' => '₦18K/month',
                    'implementation_time' => '2 weeks',
                    'priority' => 'medium'
                ],
                [
                    'opportunity' => 'Automated quality control',
                    'potential_savings' => '₦12K/month',
                    'implementation_time' => '1 month',
                    'priority' => 'medium'
                ]
            ],
            'long_term_opportunities' => [
                [
                    'opportunity' => 'Vertical integration',
                    'potential_savings' => '₦150K/month',
                    'implementation_time' => '6 months',
                    'priority' => 'low'
                ],
                [
                    'opportunity' => 'Automated inventory management',
                    'potential_savings' => '₦45K/month',
                    'implementation_time' => '3 months',
                    'priority' => 'medium'
                ]
            ],
            'total_potential_savings' => '₦250K/month',
            'implementation_priority' => 'high'
        ];
    }

    /**
     * Get efficiency metrics
     */
    private function getEfficiencyMetrics()
    {
        return [
            'overall_efficiency_score' => 87,
            'department_efficiency' => [
                'production' => 85,
                'logistics' => 88,
                'quality_control' => 94,
                'telesales' => 92
            ],
            'cost_performance' => [
                'cost_variance' => '-5%',
                'budget_adherence' => '95%',
                'cost_trend' => 'improving'
            ],
            'quality_metrics' => [
                'defect_rate' => '2.1%',
                'customer_satisfaction' => '94.5%',
                'return_rate' => '1.8%'
            ],
            'productivity_metrics' => [
                'units_per_hour' => 8.5,
                'orders_per_day' => 47,
                'deliveries_per_da' => 3.2
            ]
        ];
    }

    /**
     * Get cost control alerts
     */
    public function getCostControlAlerts(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_DANGOTE_COST_CONTROL) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Dangote Cost Control access required.'
                ], 403);
            }

            $alerts = [
                [
                    'id' => 1,
                    'type' => 'cost_deviation',
                    'severity' => 'medium',
                    'title' => 'Bottle Cost Increase',
                    'description' => 'Bottle cost increased by 8% from TopPak Ltd',
                    'date' => '2024-12-06',
                    'status' => 'investigating'
                ],
                [
                    'id' => 2,
                    'type' => 'waste_alert',
                    'severity' => 'high',
                    'title' => 'Production Yield Drop',
                    'description' => 'Yield dropped to 17/20 units (15% waste)',
                    'date' => '2024-12-05',
                    'status' => 'resolved'
                ],
                [
                    'id' => 3,
                    'type' => 'optimization_opportunity',
                    'severity' => 'low',
                    'title' => 'Bulk Purchase Opportunity',
                    'description' => 'Potential ₦25K/month savings with bulk purchase',
                    'date' => '2024-12-08',
                    'status' => 'planned'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'alerts' => $alerts,
                    'summary' => [
                        'total_alerts' => count($alerts),
                        'critical' => 0,
                        'medium' => 1,
                        'low' => 1,
                        'high' => 1
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load cost control alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
