<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // DA DISTANCE MATRIX
        if (!Schema::hasTable('da_distance_matrix')) {
            Schema::create('da_distance_matrix', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('from_da_id');
                $table->unsignedBigInteger('to_da_id');
                $table->decimal('distance_km', 8, 2);
                $table->integer('travel_time_minutes');
                $table->decimal('transport_cost', 8, 2);
                $table->string('route_quality'); // good, fair, poor
                $table->json('route_waypoints')->nullable();
                $table->timestamps();
                $table->unique(['from_da_id', 'to_da_id']);
                $table->index(['from_da_id', 'distance_km']);
            });
        }

        // REGIONAL PERFORMANCE TRACKING
        if (!Schema::hasTable('regional_performance')) {
            Schema::create('regional_performance', function (Blueprint $table) {
                $table->id();
                $table->string('region_code'); // SW-LAG, NC-ABJ, etc.
                $table->string('state');
                $table->string('city');
                $table->date('performance_date');
                $table->integer('total_stock');
                $table->integer('units_sold');
                $table->decimal('sell_through_rate', 5, 2);
                $table->integer('days_of_inventory');
                $table->decimal('velocity_score', 5, 2);
                $table->json('seasonal_factors')->nullable();
                $table->timestamps();
                $table->unique(['region_code', 'performance_date']);
            });
        }

        // TRANSFER RECOMMENDATIONS
        if (!Schema::hasTable('transfer_recommendations')) {
            Schema::create('transfer_recommendations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('from_da_id');
                $table->unsignedBigInteger('to_da_id');
                $table->integer('recommended_quantity');
                $table->string('priority'); // critical, high, medium, low
                $table->decimal('potential_savings', 10, 2);
                $table->text('reasoning');
                $table->json('logistics_data');
                $table->string('status')->default('pending'); // pending, approved, in_transit, completed
                $table->timestamp('recommended_at');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'priority']);
            });
        }

        // GEOGRAPHIC ZONES
        if (!Schema::hasTable('geographic_zones')) {
            Schema::create('geographic_zones', function (Blueprint $table) {
                $table->id();
                $table->string('zone_code')->unique(); // SW, SE, NC, NE, NW, SS
                $table->string('zone_name');
                $table->json('states_included');
                $table->unsignedBigInteger('hub_da_id')->nullable(); // Central DA for zone
                $table->decimal('avg_transport_cost_per_km', 8, 2);
                $table->json('seasonal_patterns');
                $table->timestamps();
            });
        }

        // STOCK VELOCITY TRACKING
        if (!Schema::hasTable('stock_velocity_logs')) {
            Schema::create('stock_velocity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_agent_id');
                $table->date('tracking_date');
                $table->integer('opening_stock');
                $table->integer('closing_stock');
                $table->integer('units_sold');
                $table->integer('units_received');
                $table->decimal('daily_velocity', 5, 2);
                $table->integer('stockout_days')->default(0);
                $table->decimal('opportunity_cost', 8, 2)->default(0);
                $table->timestamps();
                $table->unique(['delivery_agent_id', 'tracking_date']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('stock_velocity_logs');
        Schema::dropIfExists('geographic_zones');
        Schema::dropIfExists('transfer_recommendations');
        Schema::dropIfExists('regional_performance');
        Schema::dropIfExists('da_distance_matrix');
    }
};
