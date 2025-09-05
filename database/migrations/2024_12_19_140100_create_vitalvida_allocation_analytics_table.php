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
        Schema::create('vitalvida_allocation_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->date('analytics_date');
            $table->integer('total_allocations');
            $table->integer('successful_deliveries');
            $table->integer('returned_items');
            $table->decimal('delivery_success_rate', 5, 2);
            $table->decimal('total_allocation_value', 12, 2);
            $table->decimal('delivered_value', 12, 2);
            $table->decimal('returned_value', 12, 2);
            $table->decimal('average_delivery_time', 8, 2)->nullable(); // Hours
            $table->decimal('performance_score', 5, 2);
            $table->decimal('efficiency_score', 5, 2);
            $table->decimal('compliance_score', 5, 2);
            $table->json('product_performance')->nullable(); // Performance by product category
            $table->json('zone_performance')->nullable(); // Performance by delivery zone
            $table->json('time_performance')->nullable(); // Performance by time of day/week
            $table->integer('customer_satisfaction_score')->nullable();
            $table->text('performance_notes')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('vitalvida_delivery_agents')->onDelete('cascade');
            
            $table->unique(['agent_id', 'analytics_date']);
            $table->index(['analytics_date']);
            $table->index(['performance_score']);
            $table->index(['delivery_success_rate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_allocation_analytics');
    }
};
