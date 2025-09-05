<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DeliveryAgentSeeder extends Seeder
{
    public function run(): void
    {
        // Create delivery agent with 'inventory' role (closest to what we need)
        DB::table('users')->insert([
            'name' => 'Delivery Agent',
            'email' => 'agent@vitalvida.com',
            'phone' => '9876543210', // Different phone to avoid unique constraint
            'password' => Hash::make('password123'),
            'role' => 'inventory', // Using valid role from roles table
            'kyc_status' => 'approved',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        echo "✅ Delivery agent created with 'inventory' role!\n";
    }
}
