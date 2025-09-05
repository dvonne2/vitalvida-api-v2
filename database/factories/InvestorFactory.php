<?php

namespace Database\Factories;

use App\Models\Investor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Investor>
 */
class InvestorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Investor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'company_name' => $this->faker->company(),
            'position' => $this->faker->jobTitle(),
            'bio' => $this->faker->paragraph(),
            'role' => $this->faker->randomElement([
                'master_readiness',
                'tomi_governance',
                'ron_scale',
                'thiel_strategy',
                'andy_tech',
                'otunba_control',
                'dangote_cost_control',
                'neil_growth'
            ]),
            'access_level' => $this->faker->randomElement(['full', 'limited', 'readonly']),
            'is_active' => true,
            'last_login_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'preferences' => json_encode([
                'notifications' => true,
                'email_alerts' => true,
                'dashboard_layout' => 'default'
            ]),
            'permissions' => json_encode([
                'can_view_financials' => true,
                'can_export_reports' => true,
                'can_upload_documents' => true
            ])
        ];
    }

    /**
     * Indicate that the investor is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the investor has full access.
     */
    public function fullAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => 'full',
        ]);
    }

    /**
     * Indicate that the investor has limited access.
     */
    public function limitedAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => 'limited',
        ]);
    }

    /**
     * Indicate that the investor has readonly access.
     */
    public function readonlyAccess(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => 'readonly',
        ]);
    }
} 