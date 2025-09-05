<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TransactionLock;
use App\Models\User;
use Carbon\Carbon;

class TransactionLockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔒 Seeding Transaction Locks...');

        // Get a user for locked_by field
        $user = User::first() ?? User::factory()->create();

        // Sample transaction locks
        $locks = [
            [
                'module' => 'Sales',
                'locked_till' => Carbon::now()->addDays(30), // Locked till next month
                'locked_by' => $user->id,
                'lock_reason' => 'Year-end closing and audit preparation. All sales transactions are locked for December 2024.',
                'locked_at' => Carbon::now()->subDays(5),
            ],
            [
                'module' => 'Payroll',
                'locked_till' => Carbon::now()->addDays(15), // Locked for 2 weeks
                'locked_by' => $user->id,
                'lock_reason' => 'Monthly payroll processing and tax calculations. No payroll changes allowed.',
                'locked_at' => Carbon::now()->subDays(2),
            ],
            [
                'module' => 'Banking',
                'locked_till' => Carbon::now()->addDays(7), // Locked for 1 week
                'locked_by' => $user->id,
                'lock_reason' => 'Bank reconciliation in progress. All banking transactions temporarily locked.',
                'locked_at' => Carbon::now()->subDays(1),
            ],
            [
                'module' => 'Inventory',
                'locked_till' => Carbon::now()->addDays(3), // Locked for 3 days
                'locked_by' => $user->id,
                'lock_reason' => 'Physical inventory count in progress. No stock movements allowed.',
                'locked_at' => Carbon::now()->subHours(12),
            ],
            [
                'module' => 'Purchases',
                'locked_till' => Carbon::now()->addDays(5), // Locked for 5 days
                'locked_by' => $user->id,
                'lock_reason' => 'Budget review and approval process. Purchase orders on hold.',
                'locked_at' => Carbon::now()->subDays(1),
            ],
        ];

        foreach ($locks as $lockData) {
            // Check if lock already exists for this module
            $existingLock = TransactionLock::where('module', $lockData['module'])
                ->where('locked_till', '>=', now()->toDateString())
                ->first();

            if (!$existingLock) {
                TransactionLock::create($lockData);
                $this->command->info("✅ Created lock for {$lockData['module']} module");
            } else {
                $this->command->info("⏭️  Lock already exists for {$lockData['module']} module");
            }
        }

        // Create some expired locks for testing
        $expiredLocks = [
            [
                'module' => 'Sales',
                'locked_till' => Carbon::now()->subDays(10), // Expired lock
                'locked_by' => $user->id,
                'lock_reason' => 'Previous month-end closing (expired)',
                'locked_at' => Carbon::now()->subDays(20),
            ],
            [
                'module' => 'Payroll',
                'locked_till' => Carbon::now()->subDays(5), // Expired lock
                'locked_by' => $user->id,
                'lock_reason' => 'Previous payroll processing (expired)',
                'locked_at' => Carbon::now()->subDays(15),
            ],
        ];

        foreach ($expiredLocks as $lockData) {
            TransactionLock::create($lockData);
            $this->command->info("✅ Created expired lock for {$lockData['module']} module");
        }

        $this->command->info('🎉 Transaction locks seeded successfully!');
        
        // Display summary
        $activeLocks = TransactionLock::where('locked_till', '>=', now()->toDateString())->count();
        $expiredLocks = TransactionLock::where('locked_till', '<', now()->toDateString())->count();
        
        $this->command->info("📊 Summary: {$activeLocks} active locks, {$expiredLocks} expired locks");
    }
} 