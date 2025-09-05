<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\DepartmentPerformance;
use App\Models\Department;
use Carbon\Carbon;

class DepartmentPerformanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = Department::all();
        
        if ($departments->isEmpty()) {
            $this->command->info('No departments found. Please run DepartmentSeeder first.');
            return;
        }

        $performanceMetrics = [
            'Sales' => [
                [
                    'metric_name' => 'Leads → Orders',
                    'target_value' => '20%',
                    'actual_value' => '18.5%',
                    'status' => 'monitor',
                    'trend' => 'declining',
                    'performance_score' => 92.5
                ],
                [
                    'metric_name' => 'Average Order Value',
                    'target_value' => '₦15,000',
                    'actual_value' => '₦14,200',
                    'status' => 'monitor',
                    'trend' => 'stable',
                    'performance_score' => 94.7
                ],
                [
                    'metric_name' => 'Customer Acquisition Cost',
                    'target_value' => '₦2,500',
                    'actual_value' => '₦2,800',
                    'status' => 'fix',
                    'trend' => 'increasing',
                    'performance_score' => 89.3
                ]
            ],
            'Media' => [
                [
                    'metric_name' => 'ROAS',
                    'target_value' => '3.5x',
                    'actual_value' => '3.2x',
                    'status' => 'monitor',
                    'trend' => 'stable',
                    'performance_score' => 91.4
                ],
                [
                    'metric_name' => 'Click-Through Rate',
                    'target_value' => '2.5%',
                    'actual_value' => '2.8%',
                    'status' => 'good',
                    'trend' => 'improving',
                    'performance_score' => 112.0
                ],
                [
                    'metric_name' => 'Cost per Lead',
                    'target_value' => '₦1,200',
                    'actual_value' => '₦1,350',
                    'status' => 'monitor',
                    'trend' => 'increasing',
                    'performance_score' => 88.9
                ]
            ],
            'Inventory' => [
                [
                    'metric_name' => 'Stockouts',
                    'target_value' => '0',
                    'actual_value' => '1',
                    'status' => 'fix',
                    'trend' => 'concerning',
                    'performance_score' => 0.0
                ],
                [
                    'metric_name' => 'Inventory Turnover',
                    'target_value' => '12x',
                    'actual_value' => '10.5x',
                    'status' => 'monitor',
                    'trend' => 'declining',
                    'performance_score' => 87.5
                ],
                [
                    'metric_name' => 'Carrying Cost',
                    'target_value' => '₦500K',
                    'actual_value' => '₦480K',
                    'status' => 'good',
                    'trend' => 'improving',
                    'performance_score' => 104.2
                ]
            ],
            'Logistics' => [
                [
                    'metric_name' => 'Delivery Rate',
                    'target_value' => '75%',
                    'actual_value' => '68%',
                    'status' => 'fix',
                    'trend' => 'declining',
                    'performance_score' => 90.7
                ],
                [
                    'metric_name' => 'Average Delivery Time',
                    'target_value' => '24h',
                    'actual_value' => '28h',
                    'status' => 'fix',
                    'trend' => 'concerning',
                    'performance_score' => 85.7
                ],
                [
                    'metric_name' => 'DA Utilization',
                    'target_value' => '85%',
                    'actual_value' => '82%',
                    'status' => 'monitor',
                    'trend' => 'stable',
                    'performance_score' => 96.5
                ]
            ],
            'Finance' => [
                [
                    'metric_name' => 'Burn Rate',
                    'target_value' => '₦2.5M',
                    'actual_value' => '₦2.8M',
                    'status' => 'monitor',
                    'trend' => 'increasing',
                    'performance_score' => 89.3
                ],
                [
                    'metric_name' => 'Cash Flow',
                    'target_value' => 'Positive',
                    'actual_value' => 'Positive',
                    'status' => 'good',
                    'trend' => 'stable',
                    'performance_score' => 100.0
                ],
                [
                    'metric_name' => 'Payment Accuracy',
                    'target_value' => '99%',
                    'actual_value' => '99.2%',
                    'status' => 'good',
                    'trend' => 'improving',
                    'performance_score' => 100.2
                ]
            ],
            'Customer Service' => [
                [
                    'metric_name' => 'Refund %',
                    'target_value' => '<3%',
                    'actual_value' => '2.1%',
                    'status' => 'good',
                    'trend' => 'improving',
                    'performance_score' => 130.0
                ],
                [
                    'metric_name' => 'Response Time',
                    'target_value' => '2h',
                    'actual_value' => '1.8h',
                    'status' => 'good',
                    'trend' => 'improving',
                    'performance_score' => 111.1
                ],
                [
                    'metric_name' => 'Customer Satisfaction',
                    'target_value' => '4.5/5',
                    'actual_value' => '4.3/5',
                    'status' => 'monitor',
                    'trend' => 'stable',
                    'performance_score' => 95.6
                ]
            ]
        ];

        foreach ($departments as $department) {
            if (isset($performanceMetrics[$department->name])) {
                foreach ($performanceMetrics[$department->name] as $metric) {
                    DepartmentPerformance::create([
                        'department_id' => $department->id,
                        'metric_name' => $metric['metric_name'],
                        'target_value' => $metric['target_value'],
                        'actual_value' => $metric['actual_value'],
                        'status' => $metric['status'],
                        'trend' => $metric['trend'],
                        'performance_score' => $metric['performance_score'],
                        'measurement_date' => Carbon::today(),
                        'notes' => 'Generated performance data for ' . $department->name
                    ]);
                }
            }
        }

        $this->command->info('Department performance data seeded successfully!');
    }
}
