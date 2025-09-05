<?php

namespace Database\Factories;

use App\Models\DeliveryAgent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryAgentFactory extends Factory
{
    protected $model = DeliveryAgent::class;

    public function definition(): array
    {
        $states = ['Lagos', 'Abuja', 'Kano', 'Rivers', 'Oyo', 'Delta'];
        $cities = [
            'Lagos' => ['Ikeja', 'Victoria Island', 'Lekki', 'Surulere'],
            'Abuja' => ['Garki', 'Wuse', 'Maitama', 'Kubwa'],
            'Kano' => ['Kano City', 'Fagge', 'Dala', 'Gwale'],
        ];
        
        $state = $this->faker->randomElement($states);
        $city = $this->faker->randomElement($cities[$state] ?? [$state]);
        
        return [
            'user_id' => User::factory()->deliveryAgent(),
            'da_code' => 'DA' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'vehicle_number' => strtoupper($this->faker->bothify('???-###??')),
            'vehicle_type' => $this->faker->randomElement(['motorcycle', 'bicycle', 'car', 'van']),
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'current_location' => $this->faker->address(),
            'state' => $state,
            'city' => $city,
            'total_deliveries' => $deliveries = $this->faker->numberBetween(10, 200),
            'successful_deliveries' => $this->faker->numberBetween(
                (int)($deliveries * 0.7), 
                $deliveries
            ),
            'returns_count' => $this->faker->numberBetween(0, (int)($deliveries * 0.1)),
            'complaints_count' => $this->faker->numberBetween(0, 5),
            'rating' => $this->faker->randomFloat(2, 3.0, 5.0),
            'total_earnings' => $this->faker->randomFloat(2, 5000, 50000),
            'commission_rate' => $this->faker->randomFloat(2, 5, 15),
            'strikes_count' => $this->faker->numberBetween(0, 3),
            'working_hours' => [
                'monday' => ['start' => '08:00', 'end' => '18:00'],
                'tuesday' => ['start' => '08:00', 'end' => '18:00'],
                'wednesday' => ['start' => '08:00', 'end' => '18:00'],
                'thursday' => ['start' => '08:00', 'end' => '18:00'],
                'friday' => ['start' => '08:00', 'end' => '18:00'],
                'saturday' => ['start' => '09:00', 'end' => '15:00'],
            ],
            'service_areas' => [$city, $this->faker->city()],
            'delivery_zones' => [$state],
            'vehicle_status' => $this->faker->randomElement(['available', 'busy', 'offline']),
            'current_capacity_used' => $this->faker->randomFloat(2, 0, 40),
            'max_capacity' => $this->faker->randomFloat(2, 30, 100),
            'average_delivery_time' => $this->faker->randomFloat(2, 15, 120),
            'last_active_at' => $this->faker->dateTimeBetween('-7 days'),
        ];
    }

    public function topPerformer()
    {
        return $this->state(function (array $attributes) {
            $deliveries = $this->faker->numberBetween(100, 300);
            return [
                'total_deliveries' => $deliveries,
                'successful_deliveries' => (int)($deliveries * 0.95),
                'rating' => $this->faker->randomFloat(2, 4.5, 5.0),
                'strikes_count' => 0,
                'complaints_count' => 0,
                'status' => 'active',
            ];
        });
    }

    public function struggling()
    {
        return $this->state(function (array $attributes) {
            $deliveries = $this->faker->numberBetween(20, 80);
            return [
                'total_deliveries' => $deliveries,
                'successful_deliveries' => (int)($deliveries * 0.6),
                'rating' => $this->faker->randomFloat(2, 2.0, 3.5),
                'strikes_count' => $this->faker->numberBetween(1, 4),
                'complaints_count' => $this->faker->numberBetween(1, 8),
            ];
        });
    }
}
