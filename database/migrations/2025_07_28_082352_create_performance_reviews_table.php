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
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            
            // Review Information
            $table->string('review_id', 20)->unique(); // REV001, REV002, etc.
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            
            // Review Period
            $table->enum('review_type', ['monthly', 'quarterly', 'annual', 'probation', 'special'])->default('annual');
            $table->date('review_period_start');
            $table->date('review_period_end');
            $table->date('review_date');
            $table->date('next_review_date')->nullable();
            
            // Review Status
            $table->enum('status', ['draft', 'submitted', 'reviewed', 'approved', 'completed', 'overdue'])->default('draft');
            $table->boolean('employee_acknowledged')->default(false);
            $table->timestamp('employee_acknowledged_at')->nullable();
            
            // Performance Scores (0.00 to 5.00)
            $table->decimal('overall_rating', 3, 2)->nullable();
            $table->decimal('job_knowledge', 3, 2)->nullable();
            $table->decimal('quality_of_work', 3, 2)->nullable();
            $table->decimal('quantity_of_work', 3, 2)->nullable();
            $table->decimal('communication', 3, 2)->nullable();
            $table->decimal('teamwork', 3, 2)->nullable();
            $table->decimal('initiative', 3, 2)->nullable();
            $table->decimal('attendance', 3, 2)->nullable();
            $table->decimal('punctuality', 3, 2)->nullable();
            $table->decimal('leadership', 3, 2)->nullable();
            $table->decimal('problem_solving', 3, 2)->nullable();
            $table->decimal('adaptability', 3, 2)->nullable();
            
            // Goals and Objectives
            $table->json('goals_achieved')->nullable();
            $table->json('goals_not_achieved')->nullable();
            $table->json('new_goals')->nullable();
            $table->decimal('goals_completion_rate', 5, 2)->nullable(); // 0.00 to 100.00
            
            // Feedback
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('recommendations')->nullable();
            $table->text('employee_comments')->nullable();
            $table->text('reviewer_comments')->nullable();
            
            // Development Plan
            $table->json('development_plan')->nullable();
            $table->json('training_recommendations')->nullable();
            $table->json('career_goals')->nullable();
            
            // Compensation Impact
            $table->boolean('eligible_for_raise')->default(false);
            $table->boolean('eligible_for_promotion')->default(false);
            $table->decimal('recommended_salary_increase', 5, 2)->nullable(); // Percentage
            $table->text('compensation_notes')->nullable();
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['review_id', 'status']);
            $table->index(['employee_id', 'review_type']);
            $table->index(['reviewer_id', 'status']);
            $table->index('review_date');
            $table->index('overall_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
