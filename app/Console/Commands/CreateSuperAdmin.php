<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create-superadmin {--email=} {--password=} {--name=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a superadmin user for the VitalVida admin portal';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Creating Superadmin User for VitalVida Admin Portal');
        $this->info('==================================================');

        // Get user input
        $name = $this->option('name') ?: $this->ask('Enter full name', 'Super Admin');
        $email = $this->option('email') ?: $this->ask('Enter email address', 'admin@vitalvida.com');
        $password = $this->option('password') ?: $this->secret('Enter password (min 8 characters)');

        // Validate input
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            $this->error("❌ User with email '{$email}' already exists!");
            return 1;
        }

        // Create the superadmin user
        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'superadmin',
                'is_active' => true,
                'email_verified_at' => now(),
                'kyc_status' => 'approved', // Superadmin doesn't need KYC
            ]);

            $this->info('✅ Superadmin user created successfully!');
            $this->info('');
            $this->info('📋 User Details:');
            $this->info("   Name: {$user->name}");
            $this->info("   Email: {$user->email}");
            $this->info("   Role: {$user->role}");
            $this->info("   Status: " . ($user->is_active ? 'Active' : 'Inactive'));
            $this->info('');
            $this->info('🔑 Login Credentials:');
            $this->info("   Email: {$email}");
            $this->info("   Password: [hidden]");
            $this->info('');
            $this->info('🌐 Next Steps:');
            $this->info('   1. Start your Laravel server: php artisan serve');
            $this->info('   2. Test login: POST /api/auth/login');
            $this->info('   3. Access admin dashboard: GET /api/admin/dashboard');
            $this->info('');
            $this->info('🎉 Your admin portal is ready!');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create superadmin user: " . $e->getMessage());
            return 1;
        }
    }
}
