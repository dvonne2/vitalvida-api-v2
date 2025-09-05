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
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            
            // Training Information
            $table->string('training_id', 20)->unique(); // TRN001, TRN002, etc.
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('type', ['onboarding', 'skill_development', 'compliance', 'leadership', 'technical', 'soft_skills', 'certification'])->default('skill_development');
            $table->enum('format', ['in_person', 'virtual', 'hybrid', 'self_paced', 'blended'])->default('virtual');
            
            // Training Details
            $table->integer('duration_hours')->default(1);
            $table->integer('duration_days')->default(1);
            $table->decimal('cost_per_participant', 15, 2)->default(0);
            $table->integer('max_participants')->nullable();
            $table->integer('min_participants')->default(1);
            
            // Content and Materials
            $table->json('modules')->nullable(); // Training modules structure
            $table->json('materials')->nullable(); // Training materials
            $table->json('prerequisites')->nullable(); // Prerequisites for the training
            $table->text('learning_objectives')->nullable();
            $table->text('assessment_criteria')->nullable();
            
            // Scheduling
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('instructor', 255)->nullable();
            $table->string('instructor_email', 255)->nullable();
            
            // Status and Tracking
            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'cancelled', 'archived'])->default('draft');
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('requires_assessment')->default(false);
            $table->boolean('provides_certification')->default(false);
            
            // Target Audience
            $table->json('target_departments')->nullable();
            $table->json('target_positions')->nullable();
            $table->json('target_employee_levels')->nullable();
            $table->json('excluded_employees')->nullable();
            
            // Completion Tracking
            $table->integer('total_enrolled')->default(0);
            $table->integer('total_completed')->default(0);
            $table->integer('total_failed')->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0); // 0.00 to 100.00
            $table->decimal('average_score', 5, 2)->nullable(); // 0.00 to 100.00
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['training_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('start_date');
            $table->index('completion_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
