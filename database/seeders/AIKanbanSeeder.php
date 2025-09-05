<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\Order;
use App\Models\AccountManager;
use App\Models\DeliveryAgent;
use App\Models\Notification;
use App\Models\KanbanMovement;
use App\Models\WhatsappMessage;

class AIKanbanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Account Managers
        $this->createAccountManagers();
        
        // Create Delivery Agents
        $this->createDeliveryAgents();
        
        // Create diverse customer scenarios for AI testing
        $this->createCustomerScenarios();
        
        // Create sample orders for AI testing
        $this->createOrderScenarios();
        
        // Create sample notifications
        $this->createNotifications();
    }
    
    private function createAccountManagers()
    {
        AccountManager::create([
            'name' => 'David Okafor',
            'rating' => 4.9,
            'specialties' => ['high_value', 'beauty_products'],
            'conversion_rate' => 94.0,
            'avg_assignment_time' => 12,
            'region' => 'Lagos, Ogun'
        ]);
        
        AccountManager::create([
            'name' => 'Sarah Akinola',
            'rating' => 4.7,
            'specialties' => ['repeat_customers', 'risk_handling'],
            'conversion_rate' => 87.0,
            'avg_assignment_time' => 18,
            'region' => 'Abuja FCT'
        ]);
        
        AccountManager::create([
            'name' => 'Mike Johnson',
            'rating' => 4.8,
            'specialties' => ['senior_management', 'high_value'],
            'conversion_rate' => 92.0,
            'avg_assignment_time' => 15,
            'region' => 'Rivers, Anambra'
        ]);
    }
    
    private function createDeliveryAgents()
    {
        // Get first user for user_id
        $user = \App\Models\User::first();
        if (!$user) {
            $user = \App\Models\User::create([
                'name' => 'System User',
                'email' => 'system@vitalvida.com',
                'password' => bcrypt('password'),
                'role' => 'admin'
            ]);
        }
        
        // Check if delivery agents already exist
        if (DeliveryAgent::count() > 0) {
            $this->command->info('Delivery agents already exist, skipping creation.');
            return;
        }
        
        DeliveryAgent::create([
            'user_id' => $user->id,
            'da_code' => 'DA201',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
            'current_location' => 'Lagos',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'total_deliveries' => 150,
            'successful_deliveries' => 132,
            'rating' => 4.5,
            'total_earnings' => 450000,
            'working_hours' => '8:00 AM - 6:00 PM',
            'service_areas' => 'Lagos, Ogun States',
            'delivery_zones' => 'Lagos, Ogun States',
            'vehicle_status' => 'available',
            'current_capacity_used' => 0,
            'max_capacity' => 50
        ]);
        
        DeliveryAgent::create([
            'user_id' => $user->id,
            'da_code' => 'DA202',
            'vehicle_type' => 'van',
            'status' => 'active',
            'current_location' => 'Anambra',
            'state' => 'Anambra',
            'city' => 'Awka',
            'total_deliveries' => 120,
            'successful_deliveries' => 107,
            'rating' => 4.6,
            'total_earnings' => 380000,
            'working_hours' => '8:00 AM - 6:00 PM',
            'service_areas' => 'Anambra, Rivers States',
            'delivery_zones' => 'Anambra, Rivers States',
            'vehicle_status' => 'available',
            'current_capacity_used' => 0,
            'max_capacity' => 50
        ]);
        
        DeliveryAgent::create([
            'user_id' => $user->id,
            'da_code' => 'DA203',
            'vehicle_type' => 'motorcycle',
            'status' => 'active',
            'current_location' => 'Abuja',
            'state' => 'FCT',
            'city' => 'Abuja',
            'total_deliveries' => 100,
            'successful_deliveries' => 85,
            'rating' => 4.4,
            'total_earnings' => 320000,
            'working_hours' => '8:00 AM - 6:00 PM',
            'service_areas' => 'Abuja FCT',
            'delivery_zones' => 'Abuja FCT',
            'vehicle_status' => 'available',
            'current_capacity_used' => 0,
            'max_capacity' => 50
        ]);
    }
    
    private function createCustomerScenarios()
    {
        // Check if customers already exist
        if (Customer::count() > 0) {
            $this->command->info('Customers already exist, skipping creation.');
            return;
        }
        
        // RISK³ customer waiting for prepayment
        Customer::create([
            'customer_id' => 'CUST001',
            'name' => 'Adebayo Johnson',
            'phone' => '+234 903 456 7890',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'abandoned_orders' => 3,
            'completed_orders' => 0,
            'risk_level' => 'RISK3',
            'requires_prepayment' => true,
            'status' => 'active'
        ]);
        
        // RISK² customer in verification
        Customer::create([
            'customer_id' => 'CUST002',
            'name' => 'Fatima Abubakar',
            'phone' => '+234 806 123 4567',
            'state' => 'FCT',
            'city' => 'Abuja',
            'abandoned_orders' => 2,
            'completed_orders' => 1,
            'risk_level' => 'RISK2',
            'status' => 'active'
        ]);
        
        // RISK¹ customer for conditional hold testing
        Customer::create([
            'customer_id' => 'CUST003',
            'name' => 'Chukwudi Okonkwo',
            'phone' => '+234 805 987 6543',
            'state' => 'Anambra',
            'city' => 'Awka',
            'abandoned_orders' => 1,
            'completed_orders' => 2,
            'risk_level' => 'RISK1',
            'status' => 'active'
        ]);
        
        // TRUSTED customer flowing normally
        Customer::create([
            'customer_id' => 'CUST004',
            'name' => 'Emeka Okafor',
            'phone' => '+234 701 987 6543',
            'state' => 'Rivers',
            'city' => 'Port Harcourt',
            'abandoned_orders' => 0,
            'completed_orders' => 1,
            'risk_level' => 'TRUSTED',
            'status' => 'active'
        ]);
        
        // High-value RISK² customer for manual review
        Customer::create([
            'customer_id' => 'CUST005',
            'name' => 'Blessing Adeola',
            'phone' => '+234 808 345 6789',
            'state' => 'Oyo',
            'city' => 'Ibadan',
            'abandoned_orders' => 2,
            'completed_orders' => 4,
            'risk_level' => 'RISK2',
            'lifetime_value' => 156000,
            'status' => 'active'
        ]);
    }
    
    private function createOrderScenarios()
    {
        // Check if orders already exist
        if (Order::count() > 0) {
            $this->command->info('Orders already exist, skipping creation.');
            return;
        }
        
        // RISK³ customer order - blocked for prepayment
        $risk3Customer = Customer::where('phone', '+234 903 456 7890')->first();
        Order::create([
            'order_number' => 'VV-2024-001',
            'customer_id' => $risk3Customer->id,
            'customer_name' => $risk3Customer->name,
            'customer_phone' => $risk3Customer->phone,
            'items' => json_encode(['SELF LOVE PLUS' => ['quantity' => 2, 'price' => 16375]]),
            'total_amount' => 32750,
            'status' => 'pending',
            'payment_status' => 'pending',
            'verification_required' => false,
            'can_auto_progress' => false,
            'ai_restrictions' => json_encode([
                'type' => 'prepayment_required',
                'reason' => 'RISK³ customer requires prepayment before processing',
                'action_required' => 'Payment proof upload'
            ])
        ]);
        
        // RISK² customer order - requires verification
        $risk2Customer = Customer::where('phone', '+234 806 123 4567')->first();
        Order::create([
            'order_number' => 'VV-2024-002',
            'customer_id' => $risk2Customer->id,
            'customer_name' => $risk2Customer->name,
            'customer_phone' => $risk2Customer->phone,
            'items' => json_encode(['SELF LOVE PLUS B2GOF' => ['quantity' => 1, 'price' => 66750]]),
            'total_amount' => 66750,
            'status' => 'assigned',
            'payment_status' => 'pending',
            'assigned_at' => now()->subMinutes(30),
            'verification_required' => true,
            'ai_restrictions' => json_encode([
                'type' => 'verification_required',
                'reason' => 'RISK² customer requires phone verification',
                'action_required' => 'Account Manager verification call'
            ])
        ]);
        
        // High-value RISK² customer order - blocked for manual review
        $highValueCustomer = Customer::where('phone', '+234 808 345 6789')->first();
        Order::create([
            'order_number' => 'VV-2024-003',
            'customer_id' => $highValueCustomer->id,
            'customer_name' => $highValueCustomer->name,
            'customer_phone' => $highValueCustomer->phone,
            'items' => json_encode(['Premium Bundle' => ['quantity' => 3, 'price' => 25000]]),
            'total_amount' => 75000,
            'status' => 'pending',
            'payment_status' => 'pending',
            'verification_required' => false,
            'can_auto_progress' => false,
            'ai_restrictions' => json_encode([
                'type' => 'manual_review_required',
                'reason' => 'High-value RISK² customer',
                'action_required' => 'Manager approval'
            ])
        ]);
        
        // TRUSTED customer order - flowing normally
        $trustedCustomer = Customer::where('phone', '+234 701 987 6543')->first();
        Order::create([
            'order_number' => 'VV-2024-004',
            'customer_id' => $trustedCustomer->id,
            'customer_name' => $trustedCustomer->name,
            'customer_phone' => $trustedCustomer->phone,
            'items' => json_encode(['Buy 1 Pomade' => ['quantity' => 1, 'price' => 25000]]),
            'total_amount' => 25000,
            'status' => 'assigned',
            'payment_status' => 'pending',
            'assigned_at' => now()->subMinutes(15)
        ]);
        
        // RISK¹ customer order - conditional hold
        $risk1Customer = Customer::where('phone', '+234 805 987 6543')->first();
        Order::create([
            'order_number' => 'VV-2024-005',
            'customer_id' => $risk1Customer->id,
            'customer_name' => $risk1Customer->name,
            'customer_phone' => $risk1Customer->phone,
            'items' => json_encode(['SELF LOVE PLUS' => ['quantity' => 1, 'price' => 32750]]),
            'total_amount' => 32750,
            'status' => 'assigned',
            'payment_status' => 'pending',
            'assigned_at' => now()->subMinutes(45),
            'ai_restrictions' => json_encode([
                'type' => 'conditional_hold',
                'reason' => 'RISK¹ customer - awaiting 2-hour confirmation window',
                'release_time' => now()->addMinutes(75)->toISOString()
            ])
        ]);
    }
    
    private function createNotifications()
    {
        // Manual review required notification
        Notification::create([
            'type' => 'manual_review_required',
            'priority' => 'high',
            'title' => 'Order Blocked for Manual Review',
            'message' => 'Order VV-2024-003 requires manual review: High-value RISK² customer',
            'data' => ['order_id' => 3, 'reason' => 'High-value RISK² customer']
        ]);
        
        // Assignment timeout notification
        Notification::create([
            'type' => 'assignment_timeout',
            'priority' => 'medium',
            'title' => 'Assignment Timeout',
            'message' => 'Order VV-2024-002 took 1800 seconds to assign (target: 30s)',
            'data' => ['order_id' => 2, 'assignment_time' => 1800]
        ]);
        
        // Payment received notification
        Notification::create([
            'type' => 'payment_received',
            'priority' => 'high',
            'title' => 'RISK³ Payment Received',
            'message' => 'Payment confirmed for Adebayo Johnson - ₦32,750',
            'data' => ['customer_id' => 1, 'order_id' => 1]
        ]);
        
        // Performance alert notification
        Notification::create([
            'type' => 'performance_alert',
            'priority' => 'medium',
            'title' => 'Customer Recovery Complete',
            'message' => 'Fatima Abubakar has completed recovery and can now use Pay on Delivery',
            'data' => ['customer_id' => 2, 'order_id' => 2]
        ]);
    }
}
