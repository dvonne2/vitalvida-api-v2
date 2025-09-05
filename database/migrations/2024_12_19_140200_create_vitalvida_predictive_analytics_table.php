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
        Schema::create('vitalvida_predictive_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('prediction_type'); // demand_forecast, stockout_prediction, performance_forecast
            $table->unsignedBigInteger('target_id'); // Product ID, Agent ID, etc.
            $table->string('target_type'); // product, agent, zone
            $table->date('prediction_date');
            $table->date('forecast_period_start');
            $table->date('forecast_period_end');
            $table->json('prediction_data'); // Forecast values, confidence intervals
            $table->decimal('confidence_score', 5, 2);
            $table->string('model_version')->default('v1.0');
            $table->json('model_parameters')->nullable();
            $table->json('input_features')->nullable(); // Features used for prediction
            $table->decimal('accuracy_score', 5, 2)->nullable(); // Actual vs predicted accuracy
            $table->boolean('is_active')->default(true);
            $table->text('prediction_notes')->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(['prediction_type', 'target_id', 'target_type']);
            $table->index(['prediction_date']);
            $table->index(['forecast_period_start', 'forecast_period_end']);
            $table->index(['confidence_score']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_predictive_analytics');
    }
};
