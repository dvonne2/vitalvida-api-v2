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
        Schema::create('analytics_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_name'); // e.g., 'payment_volume', 'inventory_turnover', 'threshold_violations'
            $table->string('metric_category'); // e.g., 'financial', 'operational', 'compliance', 'performance'
            $table->string('metric_type'); // e.g., 'counter', 'gauge', 'histogram', 'summary'
            $table->decimal('metric_value', 15, 4);
            $table->string('unit')->nullable(); // e.g., 'NGN', 'count', 'percentage'
            $table->json('dimensions')->nullable(); // Additional context like user_id, warehouse_id, etc.
            $table->timestamp('recorded_at');
            $table->string('data_source'); // e.g., 'payment_engine', 'inventory_system', 'threshold_system'
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['metric_name', 'recorded_at']);
            $table->index(['metric_category', 'recorded_at']);
            $table->index(['data_source', 'recorded_at']);
            $table->index('recorded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_metrics');
    }
}; 