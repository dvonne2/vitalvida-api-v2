<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\DeliveryAgent;
use App\Models\Bin;

class TestLinkDasToBinsAndUsers extends Command
{
    protected $signature = 'test:link-das-to-bins-and-users 
                            {--reset : Reset all links and start fresh}
                            {--details : Show detailed output}';

    protected $description = 'TEMPORARY: Link Delivery Agents to Users and Bins for testing (will be replaced by KYC UI)';

    public function handle()
    {
        $this->warn('⚠️  TEMPORARY COMMAND - This will be replaced by KYC UI workflow');
        $this->newLine();

        if ($this->option('reset')) {
            $this->resetAllLinks();
        }

        $this->info('🔗 STEP 1: Linking Delivery Agents to Users...');
        $this->linkDeliveryAgentsToUsers();

        $this->newLine();
        $this->info('🔗 STEP 2: Linking Bins to Delivery Agents...');
        $this->linkBinsToDeliveryAgents();

        $this->newLine();
        $this->info('🔍 STEP 3: Verification Summary...');
        $this->showLinkageSummary();

        $this->newLine();
        $this->comment('💡 Note: This temporary linking will be replaced by KYC UI where:');
        $this->comment('   - Users submit KYC documents');
        $this->comment('   - Admins manually approve and assign bins');
        $this->comment('   - Full audit trail is maintained');
    }

    private function resetAllLinks()
    {
        $this->warn('🔄 Resetting all temporary links...');
        
        DeliveryAgent::query()->update(['user_id' => null]);
        Bin::query()->update(['assigned_to_da' => null, 'da_phone' => null]);
        
        $this->info('✅ All links reset');
        $this->newLine();
    }

    private function linkDeliveryAgentsToUsers()
    {
        $unlinkedAgents = DeliveryAgent::whereNull('user_id')->get();
        
        if ($unlinkedAgents->isEmpty()) {
            $this->info('   ✅ All delivery agents already have users assigned');
            return;
        }

        $availableUsers = User::where('role', 'delivery_agent')
            ->whereDoesntHave('deliveryAgent')
            ->get();

        $linkedCount = 0;

        foreach ($unlinkedAgents as $index => $agent) {
            if (isset($availableUsers[$index])) {
                $user = $availableUsers[$index];
                $agent->user_id = $user->id;
                $agent->save();
                
                if ($this->option('details')) {
                    $this->line("   🔗 Linked DA {$agent->da_code} → User {$user->name} ({$user->email})");
                }
                $linkedCount++;
            } else {
                $user = User::create([
                    'name' => "Agent {$agent->da_code}",
                    'email' => "agent.{$agent->da_code}@test.com",
                    'phone' => '0801' . str_pad($agent->id, 7, '0', STR_PAD_LEFT),
                    'password' => bcrypt('password'),
                    'role' => 'delivery_agent',
                    'kyc_status' => 'approved',
                    'is_active' => true,
                    'state' => $agent->state ?? 'Lagos',
                    'city' => $agent->city ?? 'Ikeja'
                ]);
                
                $agent->user_id = $user->id;
                $agent->save();
                
                if ($this->option('details')) {
                    $this->line("   🆕 Created & Linked DA {$agent->da_code} → New User {$user->email}");
                }
                $linkedCount++;
            }
        }

        $this->info("   ✅ Linked {$linkedCount} delivery agents to users");
    }

    private function linkBinsToDeliveryAgents()
    {
        $unassignedBins = Bin::whereNull('assigned_to_da')->get();
        
        if ($unassignedBins->isEmpty()) {
            $this->info('   ✅ All bins already assigned to delivery agents');
            return;
        }

        $agentsWithoutBins = DeliveryAgent::with('user')
            ->get()
            ->filter(function ($agent) {
                return Bin::where('assigned_to_da', $agent->da_code)->count() === 0;
            });

        $linkedCount = 0;

        foreach ($agentsWithoutBins as $index => $agent) {
            if (isset($unassignedBins[$index])) {
                $bin = $unassignedBins[$index];
                $bin->assigned_to_da = $agent->da_code;
                $bin->da_phone = $agent->user ? $agent->user->phone : null;
                $bin->save();
                
                if ($this->option('details')) {
                    $this->line("   🔗 Linked Bin {$bin->name} → DA {$agent->da_code}");
                }
                $linkedCount++;
            }
        }

        $stillWithoutBins = DeliveryAgent::with('user')
            ->get()
            ->filter(function ($agent) {
                return Bin::where('assigned_to_da', $agent->da_code)->count() === 0;
            });

        foreach ($stillWithoutBins as $agent) {
            $bin = Bin::create([
                'name' => "Bin - {$agent->da_code}",
                'assigned_to_da' => $agent->da_code,
                'da_phone' => $agent->user ? $agent->user->phone : null,
                'location' => $agent->current_location ?: 'Lagos, Nigeria',
                'status' => 'active',
                'type' => 'delivery',
                'bin_type' => 'delivery_agent',
                'max_capacity' => 1000,
                'current_stock_count' => 0,
                'is_active' => true,
                'state' => $agent->state ?: 'Lagos'
            ]);
            
            if ($this->option('details')) {
                $this->line("   🆕 Created Bin {$bin->name} → DA {$agent->da_code}");
            }
            $linkedCount++;
        }

        $this->info("   ✅ Linked/Created {$linkedCount} bins for delivery agents");
    }

    private function showLinkageSummary()
    {
        $totalUsers = User::count();
        $daUsers = User::where('role', 'delivery_agent')->count();
        $totalAgents = DeliveryAgent::count();
        $agentsWithUsers = DeliveryAgent::whereNotNull('user_id')->count();
        $totalBins = Bin::count();
        $assignedBins = Bin::whereNotNull('assigned_to_da')->count();

        $this->table(
            ['Metric', 'Count', 'Status'],
            [
                ['Total Users', $totalUsers, '📊'],
                ['Users with DA Role', $daUsers, '🚚'],
                ['Total Delivery Agents', $totalAgents, '👥'],
                ['DAs with Users', $agentsWithUsers, $agentsWithUsers === $totalAgents ? '✅' : '⚠️'],
                ['Total Bins', $totalBins, '📦'],
                ['Bins Assigned to DAs', $assignedBins, $assignedBins >= $totalAgents ? '✅' : '⚠️'],
            ]
        );

        if ($this->option('details')) {
            $this->newLine();
            $this->info('🔗 Individual Linkages:');
            
            $agents = DeliveryAgent::with('user')->get();
            foreach ($agents as $agent) {
                $userName = $agent->user ? $agent->user->name : '❌ NO USER';
                $userEmail = $agent->user ? $agent->user->email : '';
                $bins = Bin::where('assigned_to_da', $agent->da_code)->get();
                $binNames = $bins->pluck('name')->join(', ') ?: '❌ NO BINS';
                
                $this->line("   👤 {$userName} ({$userEmail}) → 🚚 {$agent->da_code} → 📦 {$binNames}");
            }
        }

        $connectionHealth = 'HEALTHY';
        if ($agentsWithUsers < $totalAgents) $connectionHealth = 'MISSING USER LINKS';
        if ($assignedBins < $totalAgents) $connectionHealth = 'MISSING BIN ASSIGNMENTS';
        
        $this->newLine();
        $status = $connectionHealth === 'HEALTHY' ? 'info' : 'warn';
        $this->$status("🔗 System Connection Health: {$connectionHealth}");
    }
}
