<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RoleTestSeeder extends Seeder
{
    public function run(): void
    {
        // First, let's see what users already exist
        $existingUsers = DB::table('users')->get();
        echo "Existing users: " . $existingUsers->count() . "\n";
        
        // Try different possible role values
        $testRoles = ['user', 'admin', 'agent', 'delivery', 'production', 'staff', 'employee'];
        
        foreach ($testRoles as $role) {
            try {
                DB::table('users')->insert([
                    'name' => "Test User {$role}",
                    'email' => "test-{$role}@vitalvida.com",
                    'phone' => '1234567890',
                    'password' => Hash::make('password123'),
                    'role' => $role,
                    'kyc_status' => 'approved',
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                echo "✅ SUCCESS: Role '{$role}' works!\n";
                break; // Stop after first success
            } catch (\Exception $e) {
                echo "❌ FAILED: Role '{$role}' - " . $e->getMessage() . "\n";
            }
        }
        
        // Show all users after seeding
        $allUsers = DB::table('users')->select('name', 'email', 'role')->get();
        foreach ($allUsers as $user) {
            echo "User: {$user->name} | Email: {$user->email} | Role: {$user->role}\n";
        }
    }
}
