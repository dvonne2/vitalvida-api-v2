<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔐 Creating production admin accounts...');

        // Create super admin
        User::firstOrCreate(
            ['email' => 'admin@vitalvida.com'],
            [
                'name' => 'System Administrator',
                'phone' => '08012345678',
                'password' => Hash::make('SecurePassword123!'),
                'role' => 'superadmin',
                'kyc_status' => 'approved',
                'is_active' => true,
            ]
        );

        // Create inventory manager
        User::firstOrCreate(
            ['email' => 'inventory@vitalvida.com'],
            [
                'name' => 'Inventory Manager',
                'phone' => '08012345679',
                'password' => Hash::make('SecurePassword123!'),
                'role' => 'inventory_manager',
                'kyc_status' => 'approved',
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Production admin accounts created');
    }
}
