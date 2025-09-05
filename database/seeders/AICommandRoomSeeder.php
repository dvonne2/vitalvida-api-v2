<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\AICreative;
use App\Models\Campaign;
use App\Models\AIInteraction;
use App\Models\RetargetingCampaign;
use App\Models\Order;
use Carbon\Carbon;

class AICommandRoomSeeder extends Seeder
{
    public function run()
    {
        $this->seedCustomers();
        $this->seedOrders();
        $this->seedAICreatives();
        $this->seedCampaigns();
        $this->seedAIInteractions();
        $this->seedRetargetingCampaigns();
    }

    private function seedCustomers()
    {
        $personas = ['fashion_conscious', 'health_focused', 'budget_conscious', 'premium_buyer', 'trend_follower'];
        $states = ['Lagos', 'Abuja', 'Kano', 'Kaduna', 'Ondo', 'Rivers', 'Delta', 'Oyo'];
        $acquisitionSources = ['meta_ads', 'tiktok_ads', 'google_ads', 'organic', 'referral', 'whatsapp'];

        for ($i = 1; $i <= 1000; $i++) {
            $age = rand(18, 65);
            $totalSpent = rand(5000, 500000);
            $ordersCount = rand(0, 15);
            $churnProbability = rand(0, 100) / 100;
            $lifetimeValue = $totalSpent * (1 + rand(0, 5));

            Customer::create([
                'customer_id' => 'VV-CUST-' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'name' => fake()->name(),
                'phone' => '+234' . rand(7000000000, 9999999999),
                'email' => fake()->email(),
                'address' => fake()->address(),
                'city' => fake()->city(),
                'state' => $states[array_rand($states)],
                'lga' => fake()->city(),
                'customer_type' => rand(1, 100) <= 20 ? 'business' : 'individual',
                'status' => 'active',
                'lifetime_value' => $totalSpent,
                'total_orders' => $ordersCount,
                'last_order_date' => $ordersCount > 0 ? now()->subDays(rand(1, 90)) : null,
                'preferences' => ['preferred_contact' => 'whatsapp'],
                
                // AI Command Room fields
                'whatsapp_id' => 'whatsapp_' . rand(100000, 999999),
                'meta_pixel_id' => 'meta_' . rand(100000, 999999),
                'total_spent' => $totalSpent,
                'orders_count' => $ordersCount,
                'last_purchase_date' => $ordersCount > 0 ? now()->subDays(rand(1, 90)) : null,
                'churn_probability' => $churnProbability,
                'lifetime_value_prediction' => $lifetimeValue,
                'preferred_contact_time' => ['10:00', '14:00', '19:00'],
                'persona_tag' => $personas[array_rand($personas)],
                'acquisition_source' => $acquisitionSources[array_rand($acquisitionSources)],
                'age' => $age,
            ]);
        }
    }

    private function seedOrders()
    {
        $customers = Customer::all();
        $products = [
            ['name' => 'Fulani Hair Gro', 'price' => 15000],
            ['name' => 'Hair Growth Oil', 'price' => 12000],
            ['name' => 'Scalp Treatment', 'price' => 8000],
            ['name' => 'Hair Vitamins', 'price' => 5000],
        ];

        for ($i = 1; $i <= 5000; $i++) {
            $customer = $customers->random();
            $product = $products[array_rand($products)];
            $quantity = rand(1, 3);
            $totalAmount = $product['price'] * $quantity;

            Order::create([
                'order_number' => 'VV-ORD-' . str_pad($i, 8, '0', STR_PAD_LEFT),
                'customer_id' => null, // Orders don't have customer_id foreign key to customers table
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'customer_email' => $customer->email,
                'delivery_address' => $customer->address,
                'items' => [
                    [
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'quantity' => $quantity,
                        'total' => $totalAmount
                    ]
                ],
                'total_amount' => $totalAmount,
                'status' => $this->getRandomOrderStatus(),
                'payment_status' => rand(1, 100) <= 85 ? 'paid' : 'pending',
                'payment_reference' => 'PAY-' . strtoupper(uniqid()),
                'created_at' => now()->subDays(rand(0, 30)),
            ]);
        }
    }

    private function seedAICreatives()
    {
        $platforms = ['meta', 'tiktok', 'google', 'youtube', 'whatsapp'];
        $types = ['text_ad', 'video_ad', 'image_ad'];
        $statuses = ['active', 'paused', 'completed'];

        for ($i = 1; $i <= 200; $i++) {
            $platform = $platforms[array_rand($platforms)];
            $cpo = rand(800, 3000);
            $ctr = rand(5, 50) / 1000; // 0.5% to 5%
            $ordersGenerated = rand(0, 50);
            $spend = $ordersGenerated * $cpo;
            $revenue = $ordersGenerated * rand(12000, 20000);

            AICreative::create([
                'type' => $types[array_rand($types)],
                'platform' => $platform,
                'prompt_used' => 'Create high-converting ad for Vitalvida hair products targeting Nigerian women',
                'content_url' => 'https://example.com/creative-' . $i . '.jpg',
                'thumbnail_url' => 'https://example.com/thumb-' . $i . '.jpg',
                'copy_text' => 'Transform your hair today with our proven formula! Join thousands of satisfied customers.',
                'performance_score' => rand(60, 95) / 100,
                'cpo' => $cpo,
                'ctr' => $ctr,
                'orders_generated' => $ordersGenerated,
                'spend' => $spend,
                'revenue' => $revenue,
                'status' => $statuses[array_rand($statuses)],
                'ai_confidence_score' => rand(70, 95) / 100,
                'target_audience' => ['age' => '25-45', 'location' => 'Nigeria', 'interest' => 'hair_care'],
                'campaign_id' => 'camp_' . rand(100000, 999999),
                'ad_set_id' => 'adset_' . rand(100000, 999999),
                'ad_id' => 'ad_' . rand(100000, 999999),
                'created_at' => now()->subDays(rand(1, 30)),
            ]);
        }
    }

    private function seedCampaigns()
    {
        $platforms = ['meta', 'tiktok', 'google', 'youtube', 'whatsapp'];
        $types = ['acquisition', 'retention', 'retargeting', 'brand_awareness'];
        $statuses = ['active', 'paused', 'completed'];

        for ($i = 1; $i <= 50; $i++) {
            $platform = $platforms[array_rand($platforms)];
            $budget = rand(50000, 500000);
            $spent = rand($budget * 0.3, $budget * 0.9);
            $ordersGenerated = rand(10, 200);
            $revenue = $ordersGenerated * rand(12000, 20000);

            Campaign::create([
                'name' => ucfirst($platform) . ' ' . ucfirst($types[array_rand($types)]) . ' Campaign ' . $i,
                'campaign_type' => $types[array_rand($types)],
                'platform' => $platform,
                'status' => $statuses[array_rand($statuses)],
                'budget' => $budget,
                'spent' => $spent,
                'target_audience' => ['age' => '25-45', 'location' => 'Nigeria'],
                'start_date' => now()->subDays(rand(1, 30)),
                'end_date' => now()->addDays(rand(1, 30)),
                'ai_optimization_enabled' => rand(1, 100) <= 70,
                'auto_scale_enabled' => rand(1, 100) <= 50,
                'target_cpo' => 1200,
                'target_ctr' => 0.015,
                'actual_cpo' => rand(800, 2000),
                'actual_ctr' => rand(8, 25) / 1000,
                'orders_generated' => $ordersGenerated,
                'revenue_generated' => $revenue,
                'roi' => $spent > 0 ? (($revenue - $spent) / $spent) * 100 : 0,
            ]);
        }
    }

    private function seedAIInteractions()
    {
        $customers = Customer::all();
        $interactionTypes = ['creative_generation', 'retargeting_message', 'churn_prevention', 'reorder_reminder'];
        $platforms = ['meta', 'tiktok', 'google', 'whatsapp', 'sms', 'email'];

        for ($i = 1; $i <= 500; $i++) {
            $customer = $customers->random();
            $conversionAchieved = rand(1, 100) <= 30; // 30% conversion rate
            $revenueGenerated = $conversionAchieved ? rand(10000, 50000) : 0;

            AIInteraction::create([
                'customer_id' => $customer->id,
                'interaction_type' => $interactionTypes[array_rand($interactionTypes)],
                'platform' => $platforms[array_rand($platforms)],
                'content_generated' => ['message' => 'Personalized AI-generated content'],
                'ai_model_used' => 'claude-3-sonnet',
                'confidence_score' => rand(70, 95) / 100,
                'response_received' => rand(1, 100) <= 60, // 60% response rate
                'conversion_achieved' => $conversionAchieved,
                'cost' => rand(50, 2000),
                'revenue_generated' => $revenueGenerated,
                'performance_metrics' => ['ctr' => rand(5, 50) / 1000, 'cpo' => rand(800, 2000)],
                'created_at' => now()->subDays(rand(0, 7)),
            ]);
        }
    }

    private function seedRetargetingCampaigns()
    {
        $customers = Customer::all();
        $platforms = ['meta', 'tiktok', 'google', 'whatsapp', 'sms', 'email'];
        $campaignTypes = ['abandoned_cart', 'reorder_reminder', 'churn_prevention', 'viral_amplification'];
        $statuses = ['scheduled', 'sent', 'delivered'];

        for ($i = 1; $i <= 300; $i++) {
            $customer = $customers->random();
            $conversionAchieved = rand(1, 100) <= 25; // 25% conversion rate
            $revenueGenerated = $conversionAchieved ? rand(8000, 40000) : 0;

            RetargetingCampaign::create([
                'customer_id' => $customer->id,
                'platform' => $platforms[array_rand($platforms)],
                'campaign_type' => $campaignTypes[array_rand($campaignTypes)],
                'status' => $statuses[array_rand($statuses)],
                'message_content' => ['headline' => 'Don\'t miss out!', 'body' => 'Special offer just for you'],
                'target_audience' => ['age' => '25-45', 'location' => 'Nigeria'],
                'scheduled_at' => now()->subDays(rand(1, 7)),
                'sent_at' => now()->subDays(rand(0, 6)),
                'response_received' => rand(1, 100) <= 50, // 50% response rate
                'conversion_achieved' => $conversionAchieved,
                'cost' => rand(25, 1000),
                'revenue_generated' => $revenueGenerated,
                'performance_metrics' => ['delivery_rate' => rand(70, 95) / 100],
                'created_at' => now()->subDays(rand(1, 7)),
            ]);
        }
    }

    private function getRandomOrderStatus()
    {
        $statuses = ['pending', 'confirmed', 'processing', 'ready_for_delivery', 'assigned', 'in_transit', 'delivered'];
        $weights = [10, 15, 20, 15, 10, 15, 15]; // Weighted distribution
        
        $random = rand(1, 100);
        $cumulative = 0;
        
        foreach ($weights as $index => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $statuses[$index];
            }
        }
        
        return 'pending';
    }
} 