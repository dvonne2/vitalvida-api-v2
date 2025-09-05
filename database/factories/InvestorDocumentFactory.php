<?php

namespace Database\Factories;

use App\Models\InvestorDocument;
use App\Models\Investor;
use App\Models\DocumentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvestorDocument>
 */
class InvestorDocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = InvestorDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'investor_id' => Investor::factory(),
            'document_category_id' => DocumentCategory::factory(),
            'title' => $this->faker->sentence(3),
            'filename' => $this->faker->uuid() . '.pdf',
            'file_path' => 'investor-documents/financials/' . $this->faker->uuid() . '.pdf',
            'status' => $this->faker->randomElement(['complete', 'in_progress', 'not_ready']),
            'completion_status' => $this->faker->randomElement(['ready', 'pending_review', 'draft']),
            'description' => $this->faker->paragraph(),
            'version' => '1.0',
            'uploaded_by' => Investor::factory(),
            'is_required' => $this->faker->boolean(80),
            'is_confidential' => $this->faker->boolean(30),
            'file_size' => $this->faker->numberBetween(100000, 5000000),
            'mime_type' => 'application/pdf',
            'checksum' => $this->faker->sha1(),
            'last_updated' => $this->faker->dateTimeBetween('-1 month', 'now')
        ];
    }

    /**
     * Indicate that the document is complete.
     */
    public function complete(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'complete',
            'completion_status' => 'ready',
        ]);
    }

    /**
     * Indicate that the document is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'completion_status' => 'pending_review',
        ]);
    }

    /**
     * Indicate that the document is not ready.
     */
    public function notReady(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'not_ready',
            'completion_status' => 'draft',
        ]);
    }

    /**
     * Indicate that the document is confidential.
     */
    public function confidential(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_confidential' => true,
        ]);
    }

    /**
     * Indicate that the document is required.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }
} 