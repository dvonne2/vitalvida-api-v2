<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:test-user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a test user for authentication';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Delete existing test user if exists
        User::where('email', 'admin@vitalvida.com')->delete();
        
        // Create new test user
        $user = User::create([
            'name' => 'VitalVida Admin',
            'email' => 'admin@vitalvida.com',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'email_verified_at' => now(),
        ]);

        $this->info('Test user created successfully!');
        $this->info('Email: admin@vitalvida.com');
        $this->info('Password: password123');
        
        return 0;
    }
}
