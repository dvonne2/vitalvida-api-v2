<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('predictive_models', function (Blueprint $table) {
            $table->id();
            $table->string('model_name');
            $table->string('model_type'); // e.g., 'forecasting', 'classification', 'regression'
            $table->string('target_metric'); // e.g., 'payment_volume', 'inventory_demand', 'threshold_violations'
            $table->json('model_config'); // Model parameters, features, algorithms
            $table->json('training_data'); // Training dataset configuration
            $table->decimal('accuracy_score', 5, 4)->nullable(); // Model accuracy
            $table->string('model_status'); // e.g., 'training', 'active', 'inactive', 'retired'
            $table->timestamp('last_trained_at')->nullable();
            $table->timestamp('last_prediction_at')->nullable();
            $table->string('created_by');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['model_type', 'model_status']);
            $table->index(['target_metric', 'model_status']);
            $table->index('model_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predictive_models');
    }
}; 