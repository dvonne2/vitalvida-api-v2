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
        Schema::create('vitalvida_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('automation_type'); // smart_allocation, predictive_restocking, compliance_monitoring
            $table->string('action_type'); // allocation_generated, order_created, violation_detected, enforcement_executed
            $table->unsignedBigInteger('target_id')->nullable(); // Related entity ID
            $table->string('target_type')->nullable(); // agent, product, supplier, violation
            $table->json('action_data'); // Detailed action information
            $table->enum('status', ['initiated', 'in_progress', 'completed', 'failed']);
            $table->text('result_summary')->nullable();
            $table->json('metrics')->nullable(); // Performance metrics, scores, etc.
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('initiated_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->string('triggered_by')->default('system'); // system, user, schedule
            $table->unsignedBigInteger('user_id')->nullable(); // If triggered by user
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['automation_type', 'action_type']);
            $table->index(['target_id', 'target_type']);
            $table->index(['status']);
            $table->index(['initiated_at']);
            $table->index(['triggered_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_automation_logs');
    }
};
