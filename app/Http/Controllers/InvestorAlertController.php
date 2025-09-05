<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\Alert;
use App\Models\Order;
use App\Models\Revenue;
use Carbon\Carbon;

class InvestorAlertController extends Controller
{
    /**
     * Get investor alerts
     */
    public function getAlerts(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $data = [
                'critical_alerts' => $this->getCriticalAlerts($investor),
                'performance_notifications' => $this->getPerformanceNotifications($investor),
                'role_specific_alerts' => $this->getRoleSpecificAlerts($investor),
                'alert_settings' => $this->getAlertSettings($investor),
                'alert_history' => $this->getAlertHistory($investor)
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
                'message' => 'Failed to load investor alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get critical alerts
     */
    private function getCriticalAlerts($investor)
    {
        return [
            [
                'type' => 'cash_position_low',
                'threshold' => '< 30 days runway',
                'current_status' => '156 days - healthy',
                'notify_roles' => ['Master', 'Otunba', 'Tomi'],
                'severity' => 'low',
                'status' => 'resolved',
                'last_updated' => '2024-12-08 10:30'
            ],
            [
                'type' => 'growth_rate_decline',
                'threshold' => 'Week-over-week decline > 10%',
                'current_status' => '+18% growth - healthy',
                'notify_roles' => ['Neil', 'Ron', 'Master'],
                'severity' => 'low',
                'status' => 'resolved',
                'last_updated' => '2024-12-08 09:15'
            ],
            [
                'type' => 'cost_overrun',
                'threshold' => 'Any cost category > 15% variance',
                'current_status' => 'Bottle cost +8% - monitoring',
                'notify_roles' => ['Dangote', 'Otunba'],
                'severity' => 'medium',
                'status' => 'investigating',
                'last_updated' => '2024-12-08 08:45'
            ],
            [
                'type' => 'quality_control_issue',
                'threshold' => 'Quality score < 95%',
                'current_status' => '98.9% quality score - excellent',
                'notify_roles' => ['Andy', 'Master'],
                'severity' => 'low',
                'status' => 'resolved',
                'last_updated' => '2024-12-08 07:30'
            ],
            [
                'type' => 'delivery_failure_rate',
                'threshold' => 'Delivery failure > 5%',
                'current_status' => '2.1% failure rate - acceptable',
                'notify_roles' => ['Otunba', 'Andy'],
                'severity' => 'low',
                'status' => 'resolved',
                'last_updated' => '2024-12-08 06:20'
            ]
        ];
    }

    /**
     * Get performance notifications
     */
    private function getPerformanceNotifications($investor)
    {
        return [
            'daily_digest' => [
                'time' => '7:30 AM',
                'status' => 'enabled',
                'recipients' => $this->getNotificationRecipients($investor->role),
                'sections' => [
                    'financial_summary',
                    'operational_metrics',
                    'growth_highlights',
                    'critical_alerts'
                ]
            ],
            'weekly_summary' => [
                'time' => 'Monday 8:00 AM',
                'status' => 'enabled',
                'recipients' => $this->getNotificationRecipients($investor->role),
                'sections' => [
                    'weekly_performance',
                    'trend_analysis',
                    'forecast_updates',
                    'strategic_insights'
                ]
            ],
            'monthly_board_pack' => [
                'time' => '1st of month',
                'status' => 'enabled',
                'recipients' => $this->getNotificationRecipients($investor->role),
                'sections' => [
                    'executive_summary',
                    'financial_review',
                    'operational_review',
                    'strategic_update'
                ]
            ],
            'real_time_alerts' => [
                'time' => 'immediate',
                'status' => 'enabled',
                'recipients' => $this->getNotificationRecipients($investor->role),
                'triggers' => [
                    'critical_threshold_breach',
                    'significant_performance_change',
                    'operational_incident',
                    'financial_anomaly'
                ]
            ]
        ];
    }

    /**
     * Get role-specific alerts
     */
    private function getRoleSpecificAlerts($investor)
    {
        $roleAlerts = [];

        switch ($investor->role) {
            case Investor::ROLE_MASTER_READINESS:
                $roleAlerts = [
                    [
                        'type' => 'document_completion',
                        'title' => 'Document Checklist Update',
                        'description' => '5 documents completed this week',
                        'severity' => 'positive',
                        'status' => 'completed'
                    ],
                    [
                        'type' => 'compliance_status',
                        'title' => 'Regulatory Compliance',
                        'description' => 'All compliance requirements met',
                        'severity' => 'positive',
                        'status' => 'completed'
                    ]
                ];
                break;

            case Investor::ROLE_TOMI_GOVERNANCE:
                $roleAlerts = [
                    [
                        'type' => 'financial_governance',
                        'title' => 'Financial Governance Score',
                        'description' => 'Governance score: 94/100',
                        'severity' => 'positive',
                        'status' => 'monitoring'
                    ],
                    [
                        'type' => 'board_metrics',
                        'title' => 'Board Governance Update',
                        'description' => 'All board metrics within targets',
                        'severity' => 'positive',
                        'status' => 'completed'
                    ]
                ];
                break;

            case Investor::ROLE_ANDY_TECH:
                $roleAlerts = [
                    [
                        'type' => 'system_performance',
                        'title' => 'API Response Time',
                        'description' => 'Average response time: 245ms',
                        'severity' => 'low',
                        'status' => 'monitoring'
                    ],
                    [
                        'type' => 'automation_opportunity',
                        'title' => 'Inventory Automation',
                        'description' => 'RFID implementation opportunity identified',
                        'severity' => 'low',
                        'status' => 'planned'
                    ]
                ];
                break;

            case Investor::ROLE_OTUNBA_CONTROL:
                $roleAlerts = [
                    [
                        'type' => 'cash_position',
                        'title' => 'Daily Cash Position',
                        'description' => 'Total available: ₦2.495M',
                        'severity' => 'positive',
                        'status' => 'monitoring'
                    ],
                    [
                        'type' => 'da_balances',
                        'title' => 'DA Balance Check',
                        'description' => 'All DAs have zero outstanding balance',
                        'severity' => 'positive',
                        'status' => 'completed'
                    ]
                ];
                break;

            case Investor::ROLE_DANGOTE_COST_CONTROL:
                $roleAlerts = [
                    [
                        'type' => 'cost_deviation',
                        'title' => 'Bottle Cost Increase',
                        'description' => 'Bottle cost increased by 8%',
                        'severity' => 'medium',
                        'status' => 'investigating'
                    ],
                    [
                        'type' => 'yield_optimization',
                        'title' => 'Production Yield',
                        'description' => 'Yield at 85% efficiency',
                        'severity' => 'low',
                        'status' => 'monitoring'
                    ]
                ];
                break;

            case Investor::ROLE_NEIL_GROWTH:
                $roleAlerts = [
                    [
                        'type' => 'growth_performance',
                        'title' => 'Neil Score Update',
                        'description' => 'Overall score: 97/100 (A+)',
                        'severity' => 'positive',
                        'status' => 'completed'
                    ],
                    [
                        'type' => 'marketing_optimization',
                        'title' => 'ROAS Performance',
                        'description' => 'Average ROAS: 4.6x across platforms',
                        'severity' => 'positive',
                        'status' => 'monitoring'
                    ]
                ];
                break;
        }

        return $roleAlerts;
    }

    /**
     * Get alert settings
     */
    private function getAlertSettings($investor)
    {
        return [
            'notification_preferences' => [
                'email' => true,
                'sms' => false,
                'push_notifications' => true,
                'dashboard_alerts' => true
            ],
            'alert_frequency' => [
                'critical_alerts' => 'immediate',
                'performance_alerts' => 'daily',
                'summary_reports' => 'weekly',
                'detailed_reports' => 'monthly'
            ],
            'threshold_settings' => [
                'cash_position_threshold' => '30 days',
                'growth_decline_threshold' => '10%',
                'cost_variance_threshold' => '15%',
                'quality_score_threshold' => '95%',
                'delivery_failure_threshold' => '5%'
            ],
            'role_specific_settings' => $this->getRoleSpecificSettings($investor->role)
        ];
    }

    /**
     * Get alert history
     */
    private function getAlertHistory($investor)
    {
        return [
            'recent_alerts' => [
                [
                    'id' => 1,
                    'type' => 'performance_alert',
                    'title' => 'Revenue Growth Achievement',
                    'description' => 'Revenue grew by 18% week-over-week',
                    'severity' => 'positive',
                    'date' => '2024-12-08 10:00',
                    'status' => 'resolved'
                ],
                [
                    'id' => 2,
                    'type' => 'operational_alert',
                    'title' => 'Quality Control Score',
                    'description' => 'Quality score improved to 98.9%',
                    'severity' => 'positive',
                    'date' => '2024-12-08 09:30',
                    'status' => 'resolved'
                ],
                [
                    'id' => 3,
                    'type' => 'financial_alert',
                    'title' => 'Cash Position Update',
                    'description' => 'Cash position: ₦2.495M (156 days runway)',
                    'severity' => 'positive',
                    'date' => '2024-12-08 09:00',
                    'status' => 'resolved'
                ],
                [
                    'id' => 4,
                    'type' => 'cost_alert',
                    'title' => 'Bottle Cost Increase',
                    'description' => 'Bottle cost increased by 8% from vendor',
                    'severity' => 'medium',
                    'date' => '2024-12-08 08:45',
                    'status' => 'investigating'
                ],
                [
                    'id' => 5,
                    'type' => 'growth_alert',
                    'title' => 'Customer Acquisition',
                    'description' => '156 new customers acquired this week',
                    'severity' => 'positive',
                    'date' => '2024-12-08 08:00',
                    'status' => 'resolved'
                ]
            ],
            'alert_statistics' => [
                'total_alerts_week' => 15,
                'critical_alerts' => 0,
                'medium_alerts' => 1,
                'low_alerts' => 8,
                'positive_alerts' => 6,
                'resolution_rate' => '93%'
            ]
        ];
    }

    /**
     * Get notification recipients
     */
    private function getNotificationRecipients($role)
    {
        $recipients = [];

        switch ($role) {
            case Investor::ROLE_MASTER_READINESS:
                $recipients = ['Master Readiness Team', 'CEO', 'CFO'];
                break;
            case Investor::ROLE_TOMI_GOVERNANCE:
                $recipients = ['Tomi Governance Team', 'Board Members', 'Legal Team'];
                break;
            case Investor::ROLE_ANDY_TECH:
                $recipients = ['Andy Tech Team', 'CTO', 'Engineering Team'];
                break;
            case Investor::ROLE_OTUNBA_CONTROL:
                $recipients = ['Otunba Control Team', 'CFO', 'Finance Team'];
                break;
            case Investor::ROLE_DANGOTE_COST_CONTROL:
                $recipients = ['Dangote Cost Control Team', 'Operations Manager', 'Procurement Team'];
                break;
            case Investor::ROLE_NEIL_GROWTH:
                $recipients = ['Neil Growth Team', 'CMO', 'Marketing Team'];
                break;
        }

        return $recipients;
    }

    /**
     * Get role-specific settings
     */
    private function getRoleSpecificSettings($role)
    {
        $settings = [];

        switch ($role) {
            case Investor::ROLE_MASTER_READINESS:
                $settings = [
                    'document_completion_threshold' => '90%',
                    'compliance_score_threshold' => '95%',
                    'investment_readiness_threshold' => '85%'
                ];
                break;
            case Investor::ROLE_TOMI_GOVERNANCE:
                $settings = [
                    'financial_governance_threshold' => '90%',
                    'board_metrics_threshold' => '85%',
                    'compliance_audit_threshold' => '100%'
                ];
                break;
            case Investor::ROLE_ANDY_TECH:
                $settings = [
                    'system_performance_threshold' => '500ms',
                    'automation_score_threshold' => '80%',
                    'quality_control_threshold' => '95%'
                ];
                break;
            case Investor::ROLE_OTUNBA_CONTROL:
                $settings = [
                    'cash_position_threshold' => '30 days',
                    'da_balance_threshold' => '0',
                    'payment_variance_threshold' => '5%'
                ];
                break;
            case Investor::ROLE_DANGOTE_COST_CONTROL:
                $settings = [
                    'cost_variance_threshold' => '15%',
                    'yield_efficiency_threshold' => '80%',
                    'waste_percentage_threshold' => '10%'
                ];
                break;
            case Investor::ROLE_NEIL_GROWTH:
                $settings = [
                    'neil_score_threshold' => '85%',
                    'roas_threshold' => '3.0x',
                    'growth_rate_threshold' => '10%'
                ];
                break;
        }

        return $settings;
    }

    /**
     * Update alert settings
     */
    public function updateAlertSettings(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $request->validate([
                'notification_preferences' => 'array',
                'alert_frequency' => 'array',
                'threshold_settings' => 'array'
            ]);

            // Update investor preferences
            $preferences = $investor->preferences ?? [];
            $preferences['alert_settings'] = $request->all();
            $investor->preferences = $preferences;
            $investor->save();

            return response()->json([
                'success' => true,
                'message' => 'Alert settings updated successfully',
                'data' => [
                    'updated_at' => now()->toISOString(),
                    'settings' => $preferences['alert_settings']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update alert settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Acknowledge alert
     */
    public function acknowledgeAlert(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $request->validate([
                'alert_id' => 'required|integer',
                'acknowledgment_note' => 'nullable|string'
            ]);

            $alertId = $request->get('alert_id');
            $note = $request->get('acknowledgment_note');

            // In a real implementation, you would update the alert status in the database
            // For now, we'll return a success response

            return response()->json([
                'success' => true,
                'message' => 'Alert acknowledged successfully',
                'data' => [
                    'alert_id' => $alertId,
                    'acknowledged_at' => now()->toISOString(),
                    'acknowledged_by' => $investor->name,
                    'note' => $note
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge alert',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
