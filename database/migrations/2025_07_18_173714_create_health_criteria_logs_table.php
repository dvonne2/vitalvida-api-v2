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
        Schema::create('health_criteria_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Accountant being tracked
            $table->date('week_start_date'); // Monday of the week
            $table->date('week_end_date'); // Sunday of the week
            $table->decimal('payment_matching_accuracy', 5, 2)->default(0.00); // 0-100%
            $table->decimal('escalation_discipline_score', 5, 2)->default(0.00); // 0-100%
            $table->decimal('documentation_integrity_score', 5, 2)->default(0.00); // 0-100%
            $table->decimal('bonus_log_accuracy_score', 5, 2)->default(0.00); // 0-100%
            $table->decimal('overall_score', 5, 2)->default(0.00); // Average of all 4
            $table->boolean('bonus_eligible')->default(false); // All criteria >= 90%
            $table->decimal('bonus_amount', 8, 2)->default(0.00); // ₦10,000 if eligible
            $table->integer('total_payments_processed')->default(0);
            $table->integer('payment_mismatches')->default(0);
            $table->integer('required_escalations')->default(0);
            $table->integer('actual_escalations')->default(0);
            $table->integer('total_transactions')->default(0);
            $table->integer('complete_documentation')->default(0);
            $table->integer('total_bonus_logs')->default(0);
            $table->integer('accurate_bonus_logs')->default(0);
            $table->enum('performance_level', ['Poor', 'Needs Improvement', 'Satisfactory', 'Good', 'Excellent'])->default('Satisfactory');
            $table->boolean('is_final')->default(false); // Final calculation for the week
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('week_start_date');
            $table->index('week_end_date');
            $table->index('overall_score');
            $table->index('bonus_eligible');
            $table->index('performance_level');
            $table->unique(['user_id', 'week_start_date']); // One record per user per week
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_criteria_logs');
    }
};
