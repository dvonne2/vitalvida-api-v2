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
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('candidate_id', 20)->unique(); // CAN001, CAN002, etc.
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            
            // Address Information
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->default('Nigeria');
            
            // Professional Information
            $table->string('current_position', 255)->nullable();
            $table->string('current_company', 255)->nullable();
            $table->integer('years_of_experience')->nullable();
            $table->text('skills')->nullable(); // JSON array of skills
            $table->text('certifications')->nullable(); // JSON array of certifications
            $table->text('languages')->nullable(); // JSON array of languages
            
            // Education
            $table->string('highest_education', 100)->nullable();
            $table->string('institution', 255)->nullable();
            $table->string('field_of_study', 255)->nullable();
            $table->integer('graduation_year')->nullable();
            $table->decimal('gpa', 3, 2)->nullable();
            
            // Documents
            $table->string('resume_path')->nullable();
            $table->string('cover_letter_path')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('github_url')->nullable();
            
            // AI Assessment
            $table->decimal('ai_score', 3, 2)->nullable(); // 0.00 to 10.00
            $table->json('ai_assessment_details')->nullable(); // Detailed AI assessment
            $table->json('skill_matches')->nullable(); // Skills that match job requirements
            $table->json('cultural_fit_score')->nullable(); // Cultural fit assessment
            $table->json('technical_assessment')->nullable(); // Technical skills assessment
            
            // Application Status
            $table->enum('status', ['new', 'screened', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected', 'withdrawn'])->default('new');
            $table->enum('source', ['website', 'linkedin', 'referral', 'job_board', 'recruitment_agency', 'social_media', 'other'])->default('website');
            
            // Preferences
            $table->decimal('expected_salary', 15, 2)->nullable();
            $table->string('preferred_location', 100)->nullable();
            $table->enum('work_preference', ['remote', 'hybrid', 'onsite'])->nullable();
            $table->date('earliest_start_date')->nullable();
            
            // Metadata
            $table->text('notes')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['candidate_id', 'status']);
            $table->index('ai_score');
            $table->index('source');
            $table->index('years_of_experience');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
