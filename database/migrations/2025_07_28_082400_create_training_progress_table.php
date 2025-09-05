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
        Schema::create('training_progress', function (Blueprint $table) {
            $table->id();
            
            // Progress Information
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('training_id')->constrained('trainings')->onDelete('cascade');
            
            // Enrollment
            $table->enum('status', ['enrolled', 'in_progress', 'completed', 'failed', 'dropped', 'certified'])->default('enrolled');
            $table->datetime('enrolled_at');
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->datetime('certified_at')->nullable();
            
            // Progress Tracking
            $table->decimal('completion_percentage', 5, 2)->default(0); // 0.00 to 100.00
            $table->integer('modules_completed')->default(0);
            $table->integer('total_modules')->default(0);
            $table->integer('time_spent_minutes')->default(0);
            
            // Assessment Results
            $table->decimal('final_score', 5, 2)->nullable(); // 0.00 to 100.00
            $table->decimal('passing_score', 5, 2)->default(70.00);
            $table->boolean('passed_assessment')->nullable();
            $table->integer('attempts_count')->default(0);
            $table->integer('max_attempts')->default(3);
            
            // Module Progress
            $table->json('module_progress')->nullable(); // Detailed progress per module
            $table->json('assessment_results')->nullable(); // Assessment results per module
            $table->json('time_tracking')->nullable(); // Time spent per module
            
            // Feedback and Notes
            $table->text('employee_feedback')->nullable();
            $table->text('instructor_feedback')->nullable();
            $table->text('notes')->nullable();
            
            // Certification
            $table->string('certificate_number')->nullable();
            $table->date('certificate_issue_date')->nullable();
            $table->date('certificate_expiry_date')->nullable();
            $table->string('certificate_path')->nullable();
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'status']);
            $table->index(['training_id', 'status']);
            $table->index('completion_percentage');
            $table->index('final_score');
            $table->index('enrolled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_progress');
    }
};
