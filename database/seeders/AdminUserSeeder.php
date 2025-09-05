<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['email' => 'admin@vitalvida.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
                'role' => 'superadmin',
                'email_verified_at' => now(),
                'is_active' => true
            ]
        );

        // Create inventory manager user
        User::firstOrCreate(
            ['email' => 'inventory@vitalvida.com'],
            [
                'name' => 'Inventory Manager',
                'password' => Hash::make('password123'),
                'role' => 'inventory',
                'email_verified_at' => now(),
                'is_active' => true
            ]
        );

        // Create test DA user
        User::firstOrCreate(
            ['email' => 'da@vitalvida.com'],
            [
                'name' => 'Test DA User',
                'password' => Hash::make('password123'),
                'role' => 'DA',
                'email_verified_at' => now(),
                'is_active' => true
            ]
        );

        $this->command->info('Admin users created successfully!');
        $this->command->info('Admin: admin@vitalvida.com / password123');
        $this->command->info('Inventory: inventory@vitalvida.com / password123');
        $this->command->info('DA: da@vitalvida.com / password123');
    }
}
