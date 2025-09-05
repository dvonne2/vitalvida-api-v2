<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // DEMAND FORECASTS TABLE
        if (!Schema::hasTable('demand_forecasts')) {
            Schema::create('demand_forecasts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_agent_id');
                $table->date('forecast_date');
                $table->string('forecast_period'); // daily, weekly, monthly
                $table->integer('predicted_demand');
                $table->decimal('confidence_score', 5, 2); // 0-100%
                $table->string('model_used'); // arima, neural_network, etc.
                $table->json('input_factors'); // seasonality, trends, events
                $table->decimal('accuracy_score', 5, 2)->nullable(); // actual vs predicted
                $table->integer('actual_demand')->nullable();
                $table->json('model_metadata'); // model parameters, version
                $table->timestamps();
                $table->unique(['delivery_agent_id', 'forecast_date', 'forecast_period']);
                $table->index(['forecast_date', 'confidence_score']);
            });
        }

        // SEASONAL PATTERNS TABLE
        if (!Schema::hasTable('seasonal_patterns')) {
            Schema::create('seasonal_patterns', function (Blueprint $table) {
                $table->id();
                $table->string('pattern_type'); // monthly, weekly, holiday, weather
                $table->string('pattern_name'); // ramadan, christmas, rainy_season
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('demand_multiplier', 5, 2); // 1.5 = 50% increase
                $table->string('affected_regions')->nullable(); // SW, NC, etc.
                $table->json('historical_data'); // past years' patterns
                $table->decimal('confidence_level', 5, 2);
                $table->text('description');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['pattern_type', 'start_date', 'end_date']);
            });
        }

        // EVENT IMPACTS TABLE
        if (!Schema::hasTable('event_impacts')) {
            Schema::create('event_impacts', function (Blueprint $table) {
                $table->id();
                $table->string('event_type'); // weather, holiday, economic, social
                $table->string('event_name');
                $table->date('event_date');
                $table->integer('impact_duration_days');
                $table->decimal('demand_impact', 5, 2); // percentage change
                $table->json('affected_locations'); // states/regions affected
                $table->string('severity'); // low, medium, high, critical
                $table->json('external_data'); // weather data, economic indicators
                $table->text('impact_description');
                $table->decimal('confidence_level', 5, 2)->default(75);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['event_type', 'event_date']);
                $table->index(['severity', 'event_date']);
            });
        }

        // PREDICTION ACCURACY TABLE
        if (!Schema::hasTable('prediction_accuracy')) {
            Schema::create('prediction_accuracy', function (Blueprint $table) {
                $table->id();
                $table->string('model_name'); // arima, neural_network, ensemble
                $table->string('prediction_type'); // demand_forecast, risk_assessment
                $table->date('evaluation_date');
                $table->decimal('accuracy_percentage', 5, 2);
                $table->decimal('mean_absolute_error', 8, 2);
                $table->decimal('root_mean_square_error', 8, 2);
                $table->integer('total_predictions');
                $table->integer('correct_predictions');
                $table->json('performance_metrics'); // precision, recall, f1
                $table->json('model_parameters'); // hyperparameters used
                $table->timestamps();
                $table->index(['model_name', 'evaluation_date']);
            });
        }

        // AUTOMATED DECISIONS TABLE
        if (!Schema::hasTable('automated_decisions')) {
            Schema::create('automated_decisions', function (Blueprint $table) {
                $table->id();
                $table->string('decision_type'); // reorder, transfer, adjustment
                $table->unsignedBigInteger('delivery_agent_id');
                $table->text('trigger_reason');
                $table->json('decision_data'); // quantity, target, parameters
                $table->decimal('confidence_score', 5, 2);
                $table->string('status')->default('pending'); // pending, executed, cancelled
                $table->timestamp('triggered_at');
                $table->timestamp('executed_at')->nullable();
                $table->json('execution_result')->nullable();
                $table->boolean('human_override')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->index(['status', 'triggered_at']);
                $table->index(['delivery_agent_id', 'decision_type']);
            });
        }

        // RISK ASSESSMENTS TABLE
        if (!Schema::hasTable('risk_assessments')) {
            Schema::create('risk_assessments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_agent_id');
                $table->date('assessment_date');
                $table->decimal('stockout_probability', 5, 2); // 0-100%
                $table->decimal('overstock_probability', 5, 2); // 0-100%
                $table->integer('days_until_stockout');
                $table->decimal('potential_lost_sales', 10, 2);
                $table->decimal('carrying_cost_risk', 10, 2);
                $table->string('risk_level'); // low, medium, high, critical
                $table->json('risk_factors'); // demand variability, supply issues
                $table->json('mitigation_suggestions'); // recommended actions
                $table->decimal('overall_risk_score', 5, 2);
                $table->timestamps();
                $table->index(['delivery_agent_id', 'assessment_date']);
                $table->index(['risk_level', 'assessment_date']);
            });
        }

        // MARKET INTELLIGENCE TABLE
        if (!Schema::hasTable('market_intelligence')) {
            Schema::create('market_intelligence', function (Blueprint $table) {
                $table->id();
                $table->string('region_code'); // SW, NC, SE, etc.
                $table->date('intelligence_date');
                $table->decimal('market_temperature', 5, 2); // 0-100 market hotness
                $table->json('demand_drivers'); // economic, social, seasonal factors
                $table->json('supply_constraints'); // logistics, availability issues
                $table->decimal('price_sensitivity', 5, 2); // customer price elasticity
                $table->json('competitor_activity'); // competitive landscape
                $table->json('external_indicators'); // economic indicators, trends
                $table->text('market_summary');
                $table->decimal('reliability_score', 5, 2); // data quality score
                $table->timestamps();
                $table->index(['region_code', 'intelligence_date']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('market_intelligence');
        Schema::dropIfExists('risk_assessments');
        Schema::dropIfExists('automated_decisions');
        Schema::dropIfExists('prediction_accuracy');
        Schema::dropIfExists('event_impacts');
        Schema::dropIfExists('seasonal_patterns');
        Schema::dropIfExists('demand_forecasts');
    }
};
