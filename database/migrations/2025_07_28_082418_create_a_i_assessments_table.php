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
        Schema::create('a_i_assessments', function (Blueprint $table) {
            $table->id();
            
            // Assessment Information
            $table->string('assessment_id', 20)->unique(); // AIA001, AIA002, etc.
            $table->enum('assessment_type', ['candidate_screening', 'performance_prediction', 'cultural_fit', 'skill_assessment', 'behavioral_analysis'])->default('candidate_screening');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'cancelled'])->default('pending');
            
            // Related Entities
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->onDelete('cascade');
            $table->foreignId('employee_id')->nullable()->constrained('employees')->onDelete('cascade');
            $table->foreignId('job_posting_id')->nullable()->constrained('job_postings')->onDelete('cascade');
            $table->foreignId('job_application_id')->nullable()->constrained('job_applications')->onDelete('cascade');
            
            // Assessment Data
            $table->json('input_data')->nullable(); // Data provided for assessment
            $table->json('assessment_criteria')->nullable(); // Criteria used for assessment
            $table->json('ai_model_used')->nullable(); // AI model and version used
            
            // Assessment Results
            $table->decimal('overall_score', 3, 2)->nullable(); // 0.00 to 10.00
            $table->decimal('technical_score', 3, 2)->nullable();
            $table->decimal('cultural_fit_score', 3, 2)->nullable();
            $table->decimal('communication_score', 3, 2)->nullable();
            $table->decimal('experience_score', 3, 2)->nullable();
            $table->decimal('potential_score', 3, 2)->nullable();
            
            // Detailed Analysis
            $table->json('skill_analysis')->nullable(); // Detailed skill assessment
            $table->json('personality_analysis')->nullable(); // Personality traits
            $table->json('behavioral_analysis')->nullable(); // Behavioral patterns
            $table->json('risk_assessment')->nullable(); // Risk factors identified
            $table->json('recommendations')->nullable(); // AI recommendations
            
            // Candidate Specific (for job applications)
            $table->json('resume_analysis')->nullable(); // Resume parsing results
            $table->json('cover_letter_analysis')->nullable(); // Cover letter analysis
            $table->json('portfolio_analysis')->nullable(); // Portfolio assessment
            $table->json('social_media_analysis')->nullable(); // Social media presence
            
            // Employee Specific (for performance)
            $table->json('performance_prediction')->nullable(); // Performance forecasting
            $table->json('retention_risk')->nullable(); // Retention risk analysis
            $table->json('career_path_prediction')->nullable(); // Career path suggestions
            $table->json('training_recommendations')->nullable(); // Training needs
            
            // Assessment Metadata
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('processing_time_seconds')->nullable();
            $table->string('ai_provider', 100)->nullable(); // OpenAI, Azure, etc.
            $table->string('model_version', 50)->nullable();
            
            // Confidence and Reliability
            $table->decimal('confidence_score', 3, 2)->nullable(); // 0.00 to 1.00
            $table->decimal('reliability_score', 3, 2)->nullable(); // 0.00 to 1.00
            $table->json('uncertainty_metrics')->nullable(); // Uncertainty in predictions
            
            // Human Review
            $table->boolean('human_reviewed')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('human_feedback')->nullable();
            $table->boolean('human_agrees_with_ai')->nullable();
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['assessment_id', 'status']);
            $table->index(['assessment_type', 'status']);
            $table->index(['candidate_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index('overall_score');
            $table->index('confidence_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_i_assessments');
    }
};
