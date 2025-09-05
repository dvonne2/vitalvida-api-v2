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
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_agent_id');
            $table->date('date');
            $table->decimal('delivery_rate', 5, 2)->default(0.00); // percentage
            $table->decimal('otp_success_rate', 5, 2)->default(0.00); // percentage
            $table->decimal('stock_accuracy', 5, 2)->default(0.00); // percentage
            $table->decimal('sales_amount', 15, 2)->default(0.00);
            $table->integer('orders_completed')->default(0);
            $table->integer('orders_total')->default(0);
            $table->integer('delivery_time_avg')->default(0); // in minutes
            $table->decimal('customer_satisfaction', 5, 2)->default(0.00); // percentage
            $table->integer('returns_count')->default(0);
            $table->integer('complaints_count')->default(0);
            $table->decimal('bonus_earned', 10, 2)->default(0.00);
            $table->decimal('penalties_incurred', 10, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents')->onDelete('cascade');

            $table->index(['delivery_agent_id', 'date']);
            $table->index('date');
            $table->index('delivery_rate');
            $table->index('otp_success_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
}; 