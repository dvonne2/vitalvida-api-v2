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
        Schema::create('ai_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('delivery_agents')->onDelete('cascade');
            
            // Validation Information
            $table->enum('validation_type', ['document', 'data', 'guarantor', 'overall', 'requirements', 'identity'])->notNull();
            $table->decimal('ai_score', 5, 2)->notNull();
            $table->decimal('confidence_level', 5, 2)->notNull();
            $table->json('validation_result')->nullable(); // Detailed AI analysis
            
            // Validation Status
            $table->boolean('passed')->default(false);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('failure_reason')->nullable();
            
            // Processing Information
            $table->timestamp('validation_date')->useCurrent();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->integer('processing_duration_ms')->nullable();
            
            // AI Model Information
            $table->string('ai_model_version', 50)->nullable();
            $table->string('ai_provider', 100)->nullable(); // OpenAI, Google, etc.
            $table->json('model_parameters')->nullable(); // Model configuration used
            
            // Risk Assessment
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->json('risk_factors')->nullable(); // Specific risk factors identified
            $table->text('risk_mitigation_suggestions')->nullable();
            
            // Manual Review
            $table->boolean('requires_manual_review')->default(false);
            $table->string('assigned_to', 100)->nullable();
            $table->timestamp('manual_review_date')->nullable();
            $table->text('manual_review_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['agent_id', 'validation_type']);
            $table->index(['ai_score', 'passed']);
            $table->index(['status', 'validation_date']);
            $table->index('risk_level');
            $table->index('requires_manual_review');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_validations');
    }
};
