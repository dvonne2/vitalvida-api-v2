<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class TestHour5 extends Command
{
    protected $signature = 'test:hour5';
    protected $description = 'Test Hour 5 implementation';

    public function handle()
    {
        $this->info('🔍 Testing Hour 5: User Profile Management');
        
        // Test 3: Authentication
        $this->info('Test 3: Creating test user...');
        $user = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'is_active' => true
            ]
        );
        $this->info('✅ Test user created');
        
        // Test profile completion
        $this->info('Test 7: Profile completion...');
        $user->update([
            'city' => 'Lagos',
            'country' => 'Nigeria',
            'bio' => 'Test bio',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male'
        ]);
        
        $user = $user->fresh();
        $this->info("Profile completion: {$user->profile_completion}%");
        $this->info("Full address: {$user->full_address}");
        $this->info("Age: " . ($user->age ?? 'Not set'));
        
        $this->info('🎉 Hour 5 tests completed!');
    }
}
