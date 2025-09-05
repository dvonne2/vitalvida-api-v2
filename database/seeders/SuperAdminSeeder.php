<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Handle superadmin user for login/testing
        $user = User::where('email', 'admin@vitalvida.com')->first();

        if ($user) {
            // Always ensure password is a proper bcrypt hash to avoid runtime errors
            $user->password = Hash::make('admin123456');
            // Avoid touching columns that might not exist in current schema
            $user->save();

            $this->command->info('🔧 Superadmin user exists — password reset to a valid bcrypt hash.');
            $this->command->info('📧 Email: admin@vitalvida.com');
            $this->command->info('🔑 Password: admin123456');
            return;
        }

        // Create minimal superadmin user (only guaranteed columns)
        $created = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@vitalvida.com',
            'password' => Hash::make('admin123456'),
        ]);

        $this->command->info('✅ Superadmin user created successfully!');
        $this->command->info('📧 Email: admin@vitalvida.com');
        $this->command->info('🔑 Password: admin123456');
        $this->command->info('');
        $this->command->info('⚠️  IMPORTANT: Change the password after first login!');
    }
} 