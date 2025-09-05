<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create CEO user
        User::create([
            'name' => 'CEO',
            'email' => 'ceo@vitalvida.com',
            'password' => bcrypt('password'),
            'role' => 'CEO',
            'email_verified_at' => now(),
        ]);

        // Create department heads
        $departments = [
            'Sales' => 'production',
            'Media' => 'production', 
            'Inventory' => 'inventory',
            'Logistics' => 'production',
            'Finance' => 'CFO',
            'Customer Service' => 'production'
        ];
        
        foreach ($departments as $department => $role) {
            User::create([
                'name' => $department . ' Head',
                'email' => strtolower(str_replace(' ', '', $department)) . '@vitalvida.com',
                'password' => bcrypt('password'),
                'role' => $role,
                'email_verified_at' => now(),
            ]);
        }

        $this->command->info('Users seeded successfully!');
    }
}
