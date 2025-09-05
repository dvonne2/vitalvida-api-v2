<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AccountManager;
use App\Models\DeliveryAgent;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Notification;

class VitalVidaCRMSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Account Managers
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
            'name' => 'Michael Adebayo',
            'rating' => 4.8,
            'specialties' => ['senior_management', 'high_value'],
            'conversion_rate' => 92.0,
            'avg_assignment_time' => 15,
            'region' => 'Lagos, Ogun'
        ]);

        AccountManager::create([
            'name' => 'Fatima Hassan',
            'rating' => 4.6,
            'specialties' => ['regional_specialist', 'customer_service'],
            'conversion_rate' => 89.0,
            'avg_assignment_time' => 20,
            'region' => 'Kano, Kaduna'
        ]);
        
        // Create Delivery Agents
        $users = \App\Models\User::all();
        
        DeliveryAgent::create([
            'user_id' => $users->first()->id,
            'da_code' => 'DA001',
            'rating' => 4.5,
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'current_location' => 'Lagos',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'total_deliveries' => 150,
            'successful_deliveries' => 132,
            'current_capacity_used' => 2,
            'max_capacity' => 15,
            'working_hours' => ['start' => '08:00', 'end' => '18:00'],
            'service_areas' => ['Lagos', 'Ogun'],
            'delivery_zones' => ['Lagos Central', 'Ogun North']
        ]);
        
        DeliveryAgent::create([
            'user_id' => $users->first()->id,
            'da_code' => 'DA002',
            'rating' => 4.6,
            'vehicle_type' => 'van',
            'status' => 'available',
            'current_location' => 'Anambra',
            'state' => 'Anambra',
            'city' => 'Awka',
            'total_deliveries' => 200,
            'successful_deliveries' => 178,
            'current_capacity_used' => 1,
            'max_capacity' => 20,
            'working_hours' => ['start' => '07:00', 'end' => '17:00'],
            'service_areas' => ['Anambra', 'Rivers'],
            'delivery_zones' => ['Anambra Central', 'Rivers South']
        ]);

        DeliveryAgent::create([
            'user_id' => $users->first()->id,
            'da_code' => 'DA003',
            'rating' => 4.4,
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'current_location' => 'Kano',
            'state' => 'Kano',
            'city' => 'Kano',
            'total_deliveries' => 120,
            'successful_deliveries' => 104,
            'current_capacity_used' => 3,
            'max_capacity' => 15,
            'working_hours' => ['start' => '08:30', 'end' => '18:30'],
            'service_areas' => ['Kano', 'Kaduna'],
            'delivery_zones' => ['Kano Central', 'Kaduna North']
        ]);

        DeliveryAgent::create([
            'user_id' => $users->first()->id,
            'da_code' => 'DA004',
            'rating' => 4.7,
            'vehicle_type' => 'truck',
            'status' => 'available',
            'current_location' => 'Enugu',
            'state' => 'Enugu',
            'city' => 'Enugu',
            'total_deliveries' => 180,
            'successful_deliveries' => 164,
            'current_capacity_used' => 0,
            'max_capacity' => 25,
            'working_hours' => ['start' => '07:30', 'end' => '17:30'],
            'service_areas' => ['Enugu', 'Ebonyi'],
            'delivery_zones' => ['Enugu Central', 'Ebonyi South']
        ]);
        
        // Create sample customers with different risk levels
        Customer::create([
            'name' => 'Blessing Adeola',
            'phone' => '+234 808 345 6789',
            'location' => 'Oyo State',
            'completed_orders' => 4,
            'abandoned_orders' => 0,
            'risk_level' => 'TRUSTED',
            'lifetime_value' => 156000
        ]);
        
        Customer::create([
            'name' => 'Fatima Abubakar',
            'phone' => '+234 806 123 4567',
            'location' => 'Abuja FCT',
            'completed_orders' => 1,
            'abandoned_orders' => 2,
            'risk_level' => 'RISK2',
            'lifetime_value' => 66750
        ]);

        Customer::create([
            'name' => 'Chinedu Okoro',
            'phone' => '+234 807 234 5678',
            'location' => 'Lagos State',
            'completed_orders' => 0,
            'abandoned_orders' => 3,
            'risk_level' => 'RISK3',
            'lifetime_value' => 0,
            'requires_prepayment' => true
        ]);

        Customer::create([
            'name' => 'Aisha Mohammed',
            'phone' => '+234 805 456 7890',
            'location' => 'Kano State',
            'completed_orders' => 2,
            'abandoned_orders' => 1,
            'risk_level' => 'RISK1',
            'lifetime_value' => 89000
        ]);

        Customer::create([
            'name' => 'Oluwaseun Johnson',
            'phone' => '+234 809 567 8901',
            'location' => 'Ogun State',
            'completed_orders' => 6,
            'abandoned_orders' => 0,
            'risk_level' => 'TRUSTED',
            'lifetime_value' => 234000
        ]);

        // Create sample orders
        $customers = Customer::all();
        $accountManagers = AccountManager::all();
        $deliveryAgents = DeliveryAgent::all();

        // Create orders for each customer
        foreach ($customers as $customer) {
            $orderCount = rand(1, 3);
            
            for ($i = 0; $i < $orderCount; $i++) {
                $amount = rand(15000, 75000);
                $order = Order::create([
                    'order_id' => Order::generateOrderId(),
                    'customer_id' => $customer->id,
                    'product_name' => 'VitalVida Beauty Product',
                    'quantity' => rand(1, 3),
                    'amount' => $amount,
                    'source' => ['facebook_ads', 'instagram', 'whatsapp', 'referral'][rand(0, 3)],
                    'status' => ['received', 'assigned_to_am', 'assigned_to_da', 'completed'][rand(0, 3)],
                    'risk_level' => $customer->risk_level,
                    'account_manager_id' => $accountManagers->random()->id,
                    'delivery_agent_id' => $deliveryAgents->random()->id,
                    'assigned_at' => now()->subMinutes(rand(5, 120)),
                    'created_at' => now()->subDays(rand(1, 30))
                ]);
            }
        }

        // Create sample notifications
        Notification::create([
            'type' => 'payment_received',
            'priority' => 'high',
            'title' => 'RISK³ Payment Received',
            'message' => 'Payment confirmed for Chinedu Okoro - ₦45,000',
            'data' => ['customer_id' => 3, 'order_id' => 1]
        ]);

        Notification::create([
            'type' => 'performance_alert',
            'priority' => 'medium',
            'title' => 'Customer Recovery Complete',
            'message' => 'Aisha Mohammed has completed recovery and can now use Pay on Delivery',
            'data' => ['customer_id' => 4, 'order_id' => 2]
        ]);

        Notification::create([
            'type' => 'assignment_timeout',
            'priority' => 'medium',
            'title' => 'Order Assignment Timeout',
            'message' => 'Order VV-2025-001 took longer than expected to assign',
            'data' => ['order_id' => 1, 'assignment_time' => 45]
        ]);

        Notification::create([
            'type' => 'system_update',
            'priority' => 'low',
            'title' => 'System Update',
            'message' => 'CRM system updated to version 2.1.0',
            'data' => ['version' => '2.1.0']
        ]);

        $this->command->info('VitalVida CRM data seeded successfully!');
    }
}
