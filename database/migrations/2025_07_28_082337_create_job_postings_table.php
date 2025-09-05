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
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            
            // Job Information
            $table->string('job_id', 20)->unique(); // JOB001, JOB002, etc.
            $table->string('title', 255);
            $table->text('description');
            $table->text('requirements')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('benefits')->nullable();
            
            // Department and Position
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            $table->foreignId('position_id')->constrained('positions')->onDelete('restrict');
            
            // Job Details
            $table->enum('type', ['full_time', 'part_time', 'contract', 'intern', 'freelance'])->default('full_time');
            $table->string('location', 100)->nullable();
            $table->boolean('remote_allowed')->default(false);
            $table->boolean('hybrid_allowed')->default(false);
            $table->integer('vacancies')->default(1);
            $table->integer('filled_positions')->default(0);
            
            // Salary Information
            $table->decimal('min_salary', 15, 2)->nullable();
            $table->decimal('max_salary', 15, 2)->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->boolean('salary_negotiable')->default(true);
            
            // Application Settings
            $table->enum('status', ['draft', 'active', 'paused', 'closed', 'archived'])->default('draft');
            $table->date('application_deadline')->nullable();
            $table->date('start_date')->nullable();
            $table->boolean('urgent_hiring')->default(false);
            $table->boolean('featured')->default(false);
            
            // AI Screening Settings
            $table->boolean('ai_screening_enabled')->default(true);
            $table->json('ai_criteria')->nullable(); // AI screening criteria
            $table->decimal('minimum_ai_score', 3, 2)->default(7.00); // Minimum AI score to pass screening
            $table->json('required_skills')->nullable(); // Skills that AI will check for
            $table->json('preferred_skills')->nullable(); // Preferred skills for bonus points
            
            // Application Tracking
            $table->integer('total_applications')->default(0);
            $table->integer('screened_applications')->default(0);
            $table->integer('interviewed_applications')->default(0);
            $table->integer('hired_applications')->default(0);
            $table->integer('rejected_applications')->default(0);
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['job_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['position_id', 'status']);
            $table->index('application_deadline');
            $table->index('urgent_hiring');
            $table->index('featured');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
