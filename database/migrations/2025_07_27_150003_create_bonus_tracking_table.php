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
        Schema::create('bonus_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accountant_id')->constrained('accountants')->onDelete('cascade');
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->decimal('goal_amount', 10, 2)->default(10000.00); // ₦10,000
            $table->integer('criteria_met')->default(0);
            $table->integer('total_criteria')->default(4);
            $table->decimal('payment_matching_accuracy', 5, 2)->default(0);
            $table->decimal('escalation_discipline_score', 5, 2)->default(0);
            $table->decimal('documentation_integrity_score', 5, 2)->default(0);
            $table->decimal('bonus_log_accuracy', 5, 2)->default(0);
            $table->decimal('bonus_amount', 10, 2)->default(0);
            $table->enum('bonus_status', ['pending', 'eligible', 'not_eligible', 'paid'])->default('pending');
            $table->boolean('fc_approved')->default(false);
            $table->timestamp('fc_approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index('accountant_id');
            $table->index(['week_start_date', 'week_end_date']);
            $table->index('bonus_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_tracking');
    }
}; 