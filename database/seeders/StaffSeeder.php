<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Staff;
use App\Models\User;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $staffTypes = ['gm', 'telesales_rep', 'delivery_agent', 'coo', 'finance'];
        $states = ['Lagos', 'Kano', 'Abuja', 'Port Harcourt', 'Benin'];
        $statuses = ['active', 'inactive', 'suspended'];

        // Create staff records for existing users
        $users = User::all();

        foreach ($users as $user) {
            // Skip if user already has staff record
            if ($user->staff()->exists()) {
                continue;
            }

            $staffType = $staffTypes[array_rand($staffTypes)];
            
            Staff::create([
                'user_id' => $user->id,
                'staff_type' => $staffType,
                'state_assigned' => $states[array_rand($states)],
                'performance_score' => rand(60, 95),
                'daily_limit' => rand(15, 30),
                'status' => $statuses[array_rand($statuses)],
                'hire_date' => fake()->dateTimeBetween('-2 years', '-1 month'),
                'guarantor_info' => [
                    'name' => fake()->name(),
                    'phone' => fake()->phoneNumber(),
                    'relationship' => 'Family Member'
                ],
                'commission_rate' => rand(3, 8),
                'target_orders' => rand(20, 50),
                'completed_orders' => rand(10, 100),
                'ghosted_orders' => rand(0, 20),
                'total_earnings' => rand(50000, 500000),
                'last_activity_date' => fake()->dateTimeBetween('-7 days', 'now'),
                'is_active' => rand(0, 10) !== 0, // 90% chance of being active
                'notes' => rand(0, 1) ? fake()->sentence() : null,
            ]);
        }

        $this->command->info('Staff records seeded successfully!');
    }
}
