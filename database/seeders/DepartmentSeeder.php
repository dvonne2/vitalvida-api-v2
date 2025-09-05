<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\User;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Sales',
                'code' => 'SALES',
                'description' => 'Sales and customer acquisition department',
                'budget' => 50000000,
                'target_revenue' => 80000000,
                'current_revenue' => 75000000,
                'employee_count' => 25,
                'status' => 'active',
                'color' => '#3B82F6',
                'icon' => 'shopping-cart',
            ],
            [
                'name' => 'Media',
                'code' => 'MEDIA',
                'description' => 'Marketing and media relations department',
                'budget' => 30000000,
                'target_revenue' => 45000000,
                'current_revenue' => 42000000,
                'employee_count' => 15,
                'status' => 'active',
                'color' => '#8B5CF6',
                'icon' => 'megaphone',
            ],
            [
                'name' => 'Inventory',
                'code' => 'INVENTORY',
                'description' => 'Inventory management and stock control',
                'budget' => 20000000,
                'target_revenue' => 25000000,
                'current_revenue' => 23000000,
                'employee_count' => 20,
                'status' => 'active',
                'color' => '#10B981',
                'icon' => 'package',
            ],
            [
                'name' => 'Logistics',
                'code' => 'LOGISTICS',
                'description' => 'Delivery and logistics operations',
                'budget' => 35000000,
                'target_revenue' => 40000000,
                'current_revenue' => 38000000,
                'employee_count' => 45,
                'status' => 'active',
                'color' => '#F59E0B',
                'icon' => 'truck',
            ],
            [
                'name' => 'Finance',
                'code' => 'FINANCE',
                'description' => 'Financial management and accounting',
                'budget' => 15000000,
                'target_revenue' => 20000000,
                'current_revenue' => 18500000,
                'employee_count' => 12,
                'status' => 'active',
                'color' => '#EF4444',
                'icon' => 'dollar-sign',
            ],
            [
                'name' => 'Customer Service',
                'code' => 'CUSTOMER_SERVICE',
                'description' => 'Customer support and service operations',
                'budget' => 25000000,
                'target_revenue' => 30000000,
                'current_revenue' => 28000000,
                'employee_count' => 30,
                'status' => 'active',
                'color' => '#06B6D4',
                'icon' => 'headphones',
            ],
        ];

        foreach ($departments as $deptData) {
            // Find a user to assign as department head (or create one if needed)
            $headUser = User::first();
            
            Department::create([
                'name' => $deptData['name'],
                'code' => $deptData['code'],
                'description' => $deptData['description'],
                'head_user_id' => $headUser?->id,
                'budget' => $deptData['budget'],
                'target_revenue' => $deptData['target_revenue'],
                'current_revenue' => $deptData['current_revenue'],
                'employee_count' => $deptData['employee_count'],
                'status' => $deptData['status'],
                'color' => $deptData['color'],
                'icon' => $deptData['icon'],
                'created_by' => $headUser?->id,
            ]);
        }

        $this->command->info('Departments seeded successfully!');
    }
}
