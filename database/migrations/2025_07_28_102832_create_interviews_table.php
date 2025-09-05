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
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            
            // Interview Information
            $table->string('interview_id', 20)->unique(); // INT001, INT002, etc.
            $table->foreignId('job_application_id')->constrained('job_applications')->onDelete('cascade');
            $table->foreignId('candidate_id')->constrained('candidates')->onDelete('cascade');
            $table->foreignId('job_posting_id')->constrained('job_postings')->onDelete('cascade');
            
            // Interview Details
            $table->enum('type', ['phone', 'video', 'onsite', 'technical', 'panel', 'final'])->default('phone');
            $table->enum('status', ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'rescheduled'])->default('scheduled');
            $table->enum('round', ['first', 'second', 'third', 'final'])->default('first');
            
            // Scheduling
            $table->datetime('scheduled_at');
            $table->datetime('started_at')->nullable();
            $table->datetime('ended_at')->nullable();
            $table->integer('duration_minutes')->default(60);
            $table->string('location', 255)->nullable();
            $table->string('meeting_link', 500)->nullable();
            
            // Interviewers
            $table->json('interviewers')->nullable(); // Array of interviewer IDs
            $table->foreignId('primary_interviewer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('interviewer_notes')->nullable();
            
            // Feedback and Scoring
            $table->decimal('overall_score', 3, 2)->nullable(); // 0.00 to 10.00
            $table->decimal('technical_score', 3, 2)->nullable();
            $table->decimal('communication_score', 3, 2)->nullable();
            $table->decimal('cultural_fit_score', 3, 2)->nullable();
            $table->decimal('experience_score', 3, 2)->nullable();
            
            // Feedback Details
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('recommendations')->nullable();
            $table->enum('recommendation', ['strong_hire', 'hire', 'consider', 'do_not_hire'])->nullable();
            $table->text('detailed_feedback')->nullable();
            
            // Technical Assessment
            $table->json('technical_questions')->nullable();
            $table->json('technical_answers')->nullable();
            $table->decimal('technical_percentage', 5, 2)->nullable();
            
            // Communication
            $table->boolean('candidate_attended')->default(true);
            $table->boolean('candidate_on_time')->default(true);
            $table->text('candidate_notes')->nullable();
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['interview_id', 'status']);
            $table->index(['job_application_id', 'status']);
            $table->index(['candidate_id', 'status']);
            $table->index('scheduled_at');
            $table->index('overall_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
