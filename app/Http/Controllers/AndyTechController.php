<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\Order;
use App\Models\Product;
use App\Models\DeliveryAgent;
use Carbon\Carbon;

class AndyTechController extends Controller
{
    /**
     * Get Andy Tech dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_ANDY_TECH) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Andy Tech access required.'
                ], 403);
            }

            $data = [
                'ingredient_product_rd' => $this->getIngredientProductRD(),
                'packaging_integrity_workflow' => $this->getPackagingIntegrityWorkflow(),
                'process_efficiency_metrics' => $this->getProcessEfficiencyMetrics(),
                'per_sku_profitability' => $this->getPerSkuProfitability(),
                'automation_scorecard' => $this->getAutomationScorecard(),
                'tech_stack_overview' => $this->getTechStackOverview(),
                'technical_edge' => $this->getTechnicalEdge(),
                'system_performance' => $this->getSystemPerformance(),
                'innovation_metrics' => $this->getInnovationMetrics()
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
                'message' => 'Failed to load Andy Tech dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ingredient and product R&D data
     */
    private function getIngredientProductRD()
    {
        return [
            'moringa_leaf_extract' => [
                'source_location' => 'Kano State, Nigeria',
                'extraction_method' => 'Cold-press (Proprietary)',
                'purity_level' => '98.7%',
                'lab_verification' => 'Batch #VV2024-156',
                'active_compounds' => 'Chlorophyll 2.1%, Vitamin C 3.4%',
                'shelf_life' => '24 months',
                'quality_score' => 98.7
            ],
            'ginger_root_complex' => [
                'processing_time' => '72-hour fermentation',
                'active_compounds' => '6-gingerol 2.3%',
                'proprietary_advantage' => 'Our cold-press method preserves 40% more active compounds vs standard heat extraction',
                'source_location' => 'Kaduna State, Nigeria',
                'extraction_method' => 'Cold-press (Proprietary)',
                'purity_level' => '97.2%',
                'lab_verification' => 'Batch #VV2024-157',
                'shelf_life' => '18 months',
                'quality_score' => 97.2
            ],
            'turmeric_curcumin_extract' => [
                'source_location' => 'Katsina State, Nigeria',
                'extraction_method' => 'Cold-press (Proprietary)',
                'active_compounds' => 'Curcumin 95.2%',
                'purity_level' => '96.8%',
                'lab_verification' => 'Batch #VV2024-158',
                'shelf_life' => '20 months',
                'quality_score' => 96.8
            ]
        ];
    }

    /**
     * Get packaging integrity workflow
     */
    private function getPackagingIntegrityWorkflow()
    {
        return [
            '1_pre_seal_verification' => [
                'status' => 'complete',
                'description' => 'Image captured: Product contents + batch number',
                'success_rate' => 99.8,
                'automation_level' => 'fully_automated'
            ],
            '2_seal_application' => [
                'status' => 'complete',
                'description' => 'Tamper-evident seal with unique QR code',
                'success_rate' => 99.5,
                'automation_level' => 'semi_automated'
            ],
            '3_da_receipt_confirmation' => [
                'status' => 'complete',
                'description' => 'Photo + GPS location + timestamp',
                'success_rate' => 98.9,
                'automation_level' => 'fully_automated'
            ],
            '4_customer_verification' => [
                'status' => 'in_progress',
                'description' => 'QR scan confirms unbroken seal at delivery',
                'success_rate' => 97.2,
                'automation_level' => 'manual_verification'
            ],
            'fraud_prevention_score' => '99.2%',
            'overall_integrity_score' => 98.9,
            'total_verifications_today' => 47,
            'failed_verifications' => 1
        ];
    }

    /**
     * Get process efficiency metrics
     */
    private function getProcessEfficiencyMetrics()
    {
        return [
            'order_to_delivery_flow' => [
                'order_processing' => '3.2 min avg',
                'inventory_pick' => '8.7 min avg',
                'package_seal' => '4.1 min avg',
                'da_assignment' => '2.8 min avg',
                'total_processing' => '18.8 min avg',
                'target_time' => '15 min avg',
                'efficiency_score' => 79.8
            ],
            'optimization_target' => 'Reduce to 15 min avg through automated inventory alerts',
            'bottlenecks' => [
                'inventory_pick' => 'Manual stock verification',
                'da_assignment' => 'Route optimization needed'
            ],
            'automation_opportunities' => [
                'automated_stock_alerts' => 'Potential 30% time reduction',
                'smart_route_optimization' => 'Potential 25% time reduction',
                'predictive_da_assignment' => 'Potential 20% time reduction'
            ]
        ];
    }

    /**
     * Get per SKU profitability
     */
    private function getPerSkuProfitability()
    {
        return [
            'moringa_capsules_60ct' => [
                'cogs' => 8400,
                'cogs_formatted' => '₦8,400',
                'selling_price' => 15000,
                'selling_price_formatted' => '₦15,000',
                'gross_margin' => '53.3%',
                'refund_rate' => '2.1%',
                'net_margin' => '51.2%',
                'units_sold_mtd' => 156,
                'revenue_mtd' => 2340000,
                'profit_mtd' => 1198080
            ],
            'ginger_complex_30ct' => [
                'cogs' => 6200,
                'cogs_formatted' => '₦6,200',
                'selling_price' => 12500,
                'selling_price_formatted' => '₦12,500',
                'gross_margin' => '50.4%',
                'refund_rate' => '1.3%',
                'net_margin' => '49.1%',
                'units_sold_mtd' => 89,
                'revenue_mtd' => 1112500,
                'profit_mtd' => 546137
            ],
            'turmeric_boost_45ct' => [
                'cogs' => 7200,
                'cogs_formatted' => '₦7,200',
                'selling_price' => 13500,
                'selling_price_formatted' => '₦13,500',
                'gross_margin' => '46.7%',
                'refund_rate' => '1.8%',
                'net_margin' => '44.9%',
                'units_sold_mtd' => 67,
                'revenue_mtd' => 904500,
                'profit_mtd' => 406080
            ]
        ];
    }

    /**
     * Get automation scorecard
     */
    private function getAutomationScorecard()
    {
        return [
            'financial_operations' => [
                'score' => '87%',
                'automated_processes' => 'Payment reconciliation, refund tracking',
                'manual_processes' => 'Vendor payment approvals',
                'automation_opportunities' => 'Automated vendor payments'
            ],
            'inventory_management' => [
                'score' => '73%',
                'processes' => 'Stock alerts, reorder points, batch tracking',
                'manual_processes' => 'Physical stock counts',
                'automation_opportunities' => 'RFID tracking, automated reordering'
            ],
            'delivery_coordination' => [
                'score' => '91%',
                'processes' => 'DA assignment, route optimization, tracking',
                'manual_processes' => 'DA performance reviews',
                'automation_opportunities' => 'AI-powered route optimization'
            ],
            'quality_control' => [
                'score' => '85%',
                'processes' => 'Package verification, seal integrity checks',
                'manual_processes' => 'Final quality inspection',
                'automation_opportunities' => 'Computer vision quality checks'
            ],
            'overall_automation' => '84%',
            'target' => '90% by Q1 2025',
            'automation_roadmap' => [
                'Q1_2025' => 'Implement RFID inventory tracking',
                'Q2_2025' => 'Deploy AI route optimization',
                'Q3_2025' => 'Computer vision quality control',
                'Q4_2025' => 'Fully automated vendor payments'
            ]
        ];
    }

    /**
     * Get tech stack overview
     */
    private function getTechStackOverview()
    {
        return [
            'backend_infrastructure' => [
                'Node.js + Express API layer',
                'PostgreSQL for transactional data',
                'Redis for session management',
                'AWS S3 for image storage',
                'Docker containerization',
                'Nginx load balancer'
            ],
            'integration_layer' => [
                'Moniepoint Payment API',
                'WhatsApp Business API',
                'Zoho CRM integration',
                'Google Maps for DA routing',
                'Twilio for SMS notifications',
                'Stripe for international payments'
            ],
            'data_analytics' => [
                'Real-time dashboard (React)',
                'Automated reporting engine',
                'ML for demand forecasting',
                'Image recognition for package verification',
                'Predictive analytics for inventory',
                'Customer behavior analysis'
            ],
            'security_stack' => [
                'JWT authentication',
                'Rate limiting',
                'Data encryption at rest',
                'SSL/TLS encryption',
                'Regular security audits',
                'GDPR compliance'
            ],
            'monitoring_tools' => [
                'New Relic for performance monitoring',
                'Sentry for error tracking',
                'LogRocket for user session replay',
                'AWS CloudWatch for infrastructure',
                'Custom analytics dashboard'
            ]
        ];
    }

    /**
     * Get technical edge
     */
    private function getTechnicalEdge()
    {
        return [
            'innovation' => 'Custom image verification algorithm reduces fake package risk by 94%',
            'proprietary_advantages' => [
                'cold_press_extraction' => '40% more active compounds preserved',
                'qr_code_verification' => '99.2% fraud prevention rate',
                'ai_route_optimization' => '25% faster delivery times',
                'predictive_inventory' => '30% reduction in stockouts'
            ],
            'patents_pending' => [
                'cold_press_extraction_method' => 'Patent application submitted',
                'package_integrity_verification' => 'Patent application submitted'
            ],
            'competitive_advantages' => [
                'proprietary_sourcing' => 'Direct relationships with Nigerian farmers',
                'quality_control' => '98.7% purity verification',
                'delivery_efficiency' => 'Same-day delivery in major cities',
                'customer_trust' => 'Visible package verification process'
            ]
        ];
    }

    /**
     * Get system performance metrics
     */
    private function getSystemPerformance()
    {
        return [
            'api_response_times' => [
                'average_response_time' => '245ms',
                'p95_response_time' => '890ms',
                'p99_response_time' => '1.2s',
                'uptime' => '99.8%'
            ],
            'database_performance' => [
                'query_execution_time' => '45ms avg',
                'connection_pool_utilization' => '67%',
                'cache_hit_rate' => '89%',
                'slow_queries' => '2 per day'
            ],
            'infrastructure_metrics' => [
                'cpu_utilization' => '34%',
                'memory_utilization' => '58%',
                'disk_usage' => '42%',
                'network_throughput' => '85 Mbps'
            ],
            'error_rates' => [
                'api_error_rate' => '0.12%',
                'payment_failure_rate' => '0.08%',
                'delivery_failure_rate' => '1.2%',
                'system_crashes' => '0 in last 30 days'
            ]
        ];
    }

    /**
     * Get innovation metrics
     */
    private function getInnovationMetrics()
    {
        return [
            'rd_investment' => [
                'percentage_of_revenue' => '8.5%',
                'amount_mtd' => 425000,
                'amount_formatted' => '₦425K',
                'focus_areas' => [
                    'extraction_methods' => '40%',
                    'automation_development' => '35%',
                    'quality_control' => '25%'
                ]
            ],
            'innovation_pipeline' => [
                'active_projects' => 6,
                'completed_projects' => 12,
                'patents_filed' => 2,
                'trade_secrets' => 5
            ],
            'technology_adoption' => [
                'new_technologies_implemented' => 4,
                'automation_improvements' => '15%',
                'efficiency_gains' => '23%',
                'cost_reductions' => '18%'
            ],
            'future_roadmap' => [
                'ai_quality_control' => 'Q1 2025',
                'blockchain_tracking' => 'Q2 2025',
                'iot_inventory_tracking' => 'Q3 2025',
                'machine_learning_optimization' => 'Q4 2025'
            ]
        ];
    }

    /**
     * Get technical alerts
     */
    public function getTechnicalAlerts(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_ANDY_TECH) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Andy Tech access required.'
                ], 403);
            }

            $alerts = [
                [
                    'id' => 1,
                    'type' => 'system_performance',
                    'severity' => 'medium',
                    'title' => 'API Response Time Increase',
                    'description' => 'Average response time increased to 890ms (target: <500ms)',
                    'date' => '2024-12-08',
                    'status' => 'investigating'
                ],
                [
                    'id' => 2,
                    'type' => 'automation_opportunity',
                    'severity' => 'low',
                    'title' => 'Inventory Automation Opportunity',
                    'description' => 'Manual stock counts can be automated with RFID',
                    'date' => '2024-12-07',
                    'status' => 'planned'
                ],
                [
                    'id' => 3,
                    'type' => 'innovation_milestone',
                    'severity' => 'positive',
                    'title' => 'Patent Application Submitted',
                    'description' => 'Cold-press extraction method patent application submitted',
                    'date' => '2024-12-06',
                    'status' => 'completed'
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
                        'positive' => 1
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load technical alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
