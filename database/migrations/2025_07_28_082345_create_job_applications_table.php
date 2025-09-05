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
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            
            // Application Information
            $table->string('application_id', 20)->unique(); // APP001, APP002, etc.
            $table->foreignId('job_posting_id')->constrained('job_postings')->onDelete('cascade');
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            
            // Application Status
            $table->enum('status', [
                'applied', 'screening', 'shortlisted', 'interview_scheduled', 
                'interviewed', 'reference_check', 'background_check', 
                'offer_sent', 'offer_accepted', 'offer_declined', 
                'hired', 'rejected', 'withdrawn'
            ])->default('applied');
            
            // Application Details
            $table->text('cover_letter')->nullable();
            $table->decimal('expected_salary', 15, 2)->nullable();
            $table->date('earliest_start_date')->nullable();
            $table->text('additional_notes')->nullable();
            
            // AI Screening Results
            $table->decimal('ai_score', 3, 2)->nullable(); // 0.00 to 10.00
            $table->json('ai_assessment')->nullable(); // Detailed AI assessment
            $table->json('skill_matches')->nullable(); // Skills that match job requirements
            $table->decimal('cultural_fit_score', 3, 2)->nullable(); // 0.00 to 10.00
            $table->decimal('technical_score', 3, 2)->nullable(); // 0.00 to 10.00
            $table->boolean('ai_recommended')->default(false);
            
            // Application Tracking
            $table->timestamp('applied_at');
            $table->timestamp('screened_at')->nullable();
            $table->timestamp('shortlisted_at')->nullable();
            $table->timestamp('interview_scheduled_at')->nullable();
            $table->timestamp('interviewed_at')->nullable();
            $table->timestamp('offer_sent_at')->nullable();
            $table->timestamp('hired_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            // Interview Information
            $table->json('interview_feedback')->nullable();
            $table->decimal('interview_score', 3, 2)->nullable(); // 0.00 to 10.00
            $table->text('interview_notes')->nullable();
            
            // Reference and Background Check
            $table->json('reference_check_results')->nullable();
            $table->json('background_check_results')->nullable();
            $table->boolean('reference_check_passed')->nullable();
            $table->boolean('background_check_passed')->nullable();
            
            // Offer Information
            $table->decimal('offered_salary', 15, 2)->nullable();
            $table->date('offer_start_date')->nullable();
            $table->text('offer_terms')->nullable();
            $table->enum('offer_status', ['pending', 'accepted', 'declined', 'expired'])->nullable();
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['application_id', 'status']);
            $table->index(['job_posting_id', 'status']);
            $table->index(['candidate_id', 'status']);
            $table->index('ai_score');
            $table->index('applied_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
