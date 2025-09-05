<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Alert;
use App\Models\Department;
use Carbon\Carbon;

class AlertSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = Department::all();
        
        $alerts = [
            [
                'title' => 'CRM System Delay',
                'message' => 'Customer relationship management system experiencing 15-minute delays in order processing',
                'type' => 'system_performance',
                'severity' => 'high',
                'department_id' => $departments->where('code', 'SALES')->first()?->id,
                'status' => 'active',
                'priority' => 'high',
                'source' => 'system_monitor',
            ],
            [
                'title' => 'Refund Spike Detected',
                'message' => 'Unusual increase in refund requests detected - 25% higher than daily average',
                'type' => 'financial_anomaly',
                'severity' => 'critical',
                'department_id' => $departments->where('code', 'FINANCE')->first()?->id,
                'status' => 'active',
                'priority' => 'urgent',
                'source' => 'fraud_detection',
            ],
            [
                'title' => 'SLA Breach - Delivery',
                'message' => 'Delivery service level agreement breached for 3 orders in Lagos zone',
                'type' => 'service_quality',
                'severity' => 'medium',
                'department_id' => $departments->where('code', 'LOGISTICS')->first()?->id,
                'status' => 'acknowledged',
                'priority' => 'normal',
                'source' => 'quality_monitor',
            ],
            [
                'title' => 'Inventory Low Stock',
                'message' => 'Critical items running low: VitalVida Shampoo (15 units remaining)',
                'type' => 'inventory',
                'severity' => 'high',
                'department_id' => $departments->where('code', 'INVENTORY')->first()?->id,
                'status' => 'active',
                'priority' => 'high',
                'source' => 'inventory_system',
            ],
            [
                'title' => 'Customer Complaint Surge',
                'message' => 'Customer service complaints increased by 40% in the last 2 hours',
                'type' => 'customer_service',
                'severity' => 'medium',
                'department_id' => $departments->where('code', 'CUSTOMER_SERVICE')->first()?->id,
                'status' => 'active',
                'priority' => 'normal',
                'source' => 'crm_system',
            ],
            [
                'title' => 'Payment Processing Error',
                'message' => 'Payment gateway experiencing intermittent failures - 5 failed transactions',
                'type' => 'payment_system',
                'severity' => 'critical',
                'department_id' => $departments->where('code', 'FINANCE')->first()?->id,
                'status' => 'resolved',
                'priority' => 'urgent',
                'source' => 'payment_gateway',
            ],
            [
                'title' => 'Media Campaign Performance',
                'message' => 'Social media campaign CTR dropped below target threshold',
                'type' => 'marketing_performance',
                'severity' => 'low',
                'department_id' => $departments->where('code', 'MEDIA')->first()?->id,
                'status' => 'active',
                'priority' => 'normal',
                'source' => 'analytics_platform',
            ],
            [
                'title' => 'Delivery Agent Unavailable',
                'message' => '3 delivery agents marked unavailable in high-demand zone',
                'type' => 'logistics',
                'severity' => 'medium',
                'department_id' => $departments->where('code', 'LOGISTICS')->first()?->id,
                'status' => 'acknowledged',
                'priority' => 'normal',
                'source' => 'logistics_system',
            ],
            [
                'title' => 'Revenue Target At Risk',
                'message' => 'Sales department revenue achievement at 85% - 15% below target',
                'type' => 'performance',
                'severity' => 'high',
                'department_id' => $departments->where('code', 'SALES')->first()?->id,
                'status' => 'active',
                'priority' => 'high',
                'source' => 'performance_monitor',
            ],
            [
                'title' => 'System Backup Failed',
                'message' => 'Automated system backup failed for the last 2 attempts',
                'type' => 'system_maintenance',
                'severity' => 'critical',
                'department_id' => null, // System-wide alert
                'status' => 'active',
                'priority' => 'urgent',
                'source' => 'backup_system',
            ],
        ];

        foreach ($alerts as $alertData) {
            Alert::create([
                'title' => $alertData['title'],
                'message' => $alertData['message'],
                'type' => $alertData['type'],
                'severity' => $alertData['severity'],
                'department_id' => $alertData['department_id'],
                'status' => $alertData['status'],
                'priority' => $alertData['priority'],
                'source' => $alertData['source'],
                'metadata' => [
                    'created_at' => Carbon::now()->subHours(rand(1, 24))->toISOString(),
                    'alert_id' => 'ALERT-' . strtoupper(uniqid()),
                ],
                'created_by' => 1,
            ]);
        }

        // Create some resolved alerts
        $resolvedAlerts = [
            [
                'title' => 'Previous CRM Issue Resolved',
                'message' => 'CRM system performance restored to normal levels',
                'type' => 'system_performance',
                'severity' => 'medium',
                'department_id' => $departments->where('code', 'SALES')->first()?->id,
                'status' => 'resolved',
                'priority' => 'normal',
                'source' => 'system_monitor',
            ],
            [
                'title' => 'Inventory Restocked',
                'message' => 'Critical inventory items have been restocked successfully',
                'type' => 'inventory',
                'severity' => 'low',
                'department_id' => $departments->where('code', 'INVENTORY')->first()?->id,
                'status' => 'resolved',
                'priority' => 'normal',
                'source' => 'inventory_system',
            ],
        ];

        foreach ($resolvedAlerts as $alertData) {
            Alert::create([
                'title' => $alertData['title'],
                'message' => $alertData['message'],
                'type' => $alertData['type'],
                'severity' => $alertData['severity'],
                'department_id' => $alertData['department_id'],
                'status' => $alertData['status'],
                'priority' => $alertData['priority'],
                'source' => $alertData['source'],
                'resolved_at' => Carbon::now()->subHours(rand(1, 6)),
                'resolved_by' => 1,
                'metadata' => [
                    'created_at' => Carbon::now()->subHours(rand(6, 48))->toISOString(),
                    'alert_id' => 'ALERT-' . strtoupper(uniqid()),
                ],
                'created_by' => 1,
            ]);
        }

        $this->command->info('Alerts seeded successfully!');
    }
}
