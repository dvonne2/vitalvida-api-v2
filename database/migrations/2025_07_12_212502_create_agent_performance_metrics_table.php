<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agent_performance_metrics')) {
            Schema::create('agent_performance_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_agent_id');
                $table->date('metric_date');
                
                $table->integer('deliveries_assigned')->default(0);
                $table->integer('deliveries_completed')->default(0);
                $table->integer('deliveries_failed')->default(0);
                $table->decimal('success_rate', 5, 2)->default(0);
                $table->decimal('average_delivery_time', 8, 2)->nullable();
                $table->decimal('total_distance_km', 8, 2)->default(0);
                $table->decimal('average_rating', 3, 2)->nullable();
                $table->decimal('total_earnings', 10, 2)->default(0);
                $table->integer('active_hours')->default(0);
                $table->integer('complaints_received')->default(0);
                
                $table->timestamps();
                
                $table->unique(['delivery_agent_id', 'metric_date']);
                $table->index('metric_date');
                $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_performance_metrics');
    }
};
