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
        Schema::create('weekly_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telesales_agent_id')->constrained()->onDelete('cascade');
            $table->date('week_start');
            $table->date('week_end');
            $table->integer('orders_assigned')->default(0);
            $table->integer('orders_delivered')->default(0);
            $table->decimal('delivery_rate', 5, 2)->default(0);
            $table->boolean('qualified')->default(false); // ≥70% delivery rate + ≥20 orders
            $table->decimal('bonus_earned', 10, 2)->default(0);
            $table->decimal('avg_response_time', 8, 2)->default(0); // minutes
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['telesales_agent_id', 'week_start']);
            $table->index(['week_start', 'qualified']);
            $table->index('delivery_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_performances');
    }
};
