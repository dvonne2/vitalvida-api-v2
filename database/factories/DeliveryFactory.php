<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryAgent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition(): array
    {
        $assignedAt = $this->faker->dateTimeBetween('-30 days');
        $pickedUpAt = $this->faker->boolean(80) ? 
            $this->faker->dateTimeBetween($assignedAt, '+2 hours') : null;
        $deliveredAt = $pickedUpAt && $this->faker->boolean(85) ? 
            $this->faker->dateTimeBetween($pickedUpAt, '+3 hours') : null;

        return [
            'delivery_code' => 'DEL-' . strtoupper($this->faker->bothify('??####')),
            'order_id' => $this->faker->numberBetween(1, 1000),
            'delivery_agent_id' => DeliveryAgent::factory(),
            'assigned_by' => User::factory()->admin(),
            'status' => $this->determineStatus($pickedUpAt, $deliveredAt),
            'pickup_location' => $this->faker->address(),
            'delivery_location' => $this->faker->address(),
            'pickup_coordinates' => [
                'lat' => $this->faker->latitude(6.0, 7.0),
                'lng' => $this->faker->longitude(3.0, 4.0)
            ],
            'delivery_coordinates' => [
                'lat' => $this->faker->latitude(6.0, 7.0),
                'lng' => $this->faker->longitude(3.0, 4.0)
            ],
            'recipient_name' => $this->faker->name(),
            'recipient_phone' => $this->faker->numerify('###########'),
            'delivery_notes' => $this->faker->optional()->sentence(),
            'assigned_at' => $assignedAt,
            'picked_up_at' => $pickedUpAt,
            'delivered_at' => $deliveredAt,
            'expected_delivery_at' => $assignedAt->modify('+4 hours'),
            'delivery_otp' => $this->faker->numerify('######'),
            'otp_verified' => $deliveredAt ? true : false,
            'otp_verified_at' => $deliveredAt,
            'delivery_attempts' => $this->faker->numberBetween(1, 3),
            'failure_reason' => !$deliveredAt && $this->faker->boolean(30) ? 
                $this->faker->randomElement([
                    'Customer not available',
                    'Wrong address',
                    'Customer refused delivery',
                    'Weather conditions'
                ]) : null,
            'distance_km' => $this->faker->randomFloat(2, 1, 50),
            'delivery_time_minutes' => $deliveredAt && $pickedUpAt ? 
                $this->faker->numberBetween(15, 180) : null,
            'customer_rating' => $deliveredAt ? $this->faker->numberBetween(3, 5) : null,
            'customer_feedback' => $deliveredAt && $this->faker->boolean(40) ? 
                $this->faker->sentence() : null,
            'agent_notes' => $this->faker->optional()->sentence(),
        ];
    }

    private function determineStatus($pickedUpAt, $deliveredAt)
    {
        if ($deliveredAt) return Delivery::STATUS_DELIVERED;
        if ($pickedUpAt) return $this->faker->randomElement([
            Delivery::STATUS_IN_TRANSIT, 
            Delivery::STATUS_FAILED
        ]);
        return Delivery::STATUS_ASSIGNED;
    }

    public function completed()
    {
        return $this->state(function (array $attributes) {
            $assignedAt = $this->faker->dateTimeBetween('-30 days');
            $pickedUpAt = $this->faker->dateTimeBetween($assignedAt, '+1 hour');
            $deliveredAt = $this->faker->dateTimeBetween($pickedUpAt, '+2 hours');
            
            return [
                'status' => Delivery::STATUS_DELIVERED,
                'assigned_at' => $assignedAt,
                'picked_up_at' => $pickedUpAt,
                'delivered_at' => $deliveredAt,
                'otp_verified' => true,
                'otp_verified_at' => $deliveredAt,
                'customer_rating' => $this->faker->numberBetween(4, 5),
                'delivery_time_minutes' => $pickedUpAt->diffInMinutes($deliveredAt),
            ];
        });
    }

    public function failed()
    {
        return $this->state(fn (array $attributes) => [
            'status' => Delivery::STATUS_FAILED,
            'delivered_at' => null,
            'otp_verified' => false,
            'failure_reason' => $this->faker->randomElement([
                'Customer not available',
                'Wrong address', 
                'Customer refused delivery'
            ]),
            'delivery_attempts' => $this->faker->numberBetween(2, 3),
        ]);
    }
}
