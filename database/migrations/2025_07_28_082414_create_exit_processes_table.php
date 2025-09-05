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
        Schema::create('exit_processes', function (Blueprint $table) {
            $table->id();
            
            // Exit Information
            $table->string('exit_id', 20)->unique(); // EXT001, EXT002, etc.
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            
            // Exit Details
            $table->enum('exit_type', ['resignation', 'termination', 'retirement', 'contract_end', 'redundancy', 'other'])->default('resignation');
            $table->date('resignation_date');
            $table->date('last_working_date');
            $table->date('exit_date');
            $table->text('reason')->nullable();
            $table->text('detailed_reason')->nullable();
            
            // Notice Period
            $table->integer('notice_period_days')->default(30);
            $table->date('notice_period_start')->nullable();
            $table->date('notice_period_end')->nullable();
            $table->boolean('notice_period_served')->default(false);
            $table->integer('notice_period_shortfall_days')->default(0);
            
            // Exit Interview
            $table->boolean('exit_interview_scheduled')->default(false);
            $table->datetime('exit_interview_date')->nullable();
            $table->foreignId('exit_interviewer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('exit_interview_feedback')->nullable();
            $table->json('exit_interview_questions')->nullable();
            $table->json('exit_interview_answers')->nullable();
            
            // Handover Process
            $table->json('handover_tasks')->nullable();
            $table->foreignId('handover_to')->nullable()->constrained('employees')->onDelete('set null');
            $table->text('handover_notes')->nullable();
            $table->boolean('handover_completed')->default(false);
            $table->timestamp('handover_completed_at')->nullable();
            
            // Exit Checklist
            $table->json('exit_checklist')->nullable();
            $table->boolean('it_equipment_returned')->default(false);
            $table->boolean('access_cards_returned')->default(false);
            $table->boolean('company_property_returned')->default(false);
            $table->boolean('email_deactivated')->default(false);
            $table->boolean('system_access_revoked')->default(false);
            $table->boolean('final_payroll_processed')->default(false);
            $table->boolean('benefits_terminated')->default(false);
            $table->boolean('exit_documentation_completed')->default(false);
            
            // Final Settlement
            $table->decimal('final_salary', 15, 2)->nullable();
            $table->decimal('gratuity_amount', 15, 2)->nullable();
            $table->decimal('severance_pay', 15, 2)->nullable();
            $table->decimal('outstanding_loans', 15, 2)->nullable();
            $table->decimal('final_settlement_amount', 15, 2)->nullable();
            $table->date('settlement_payment_date')->nullable();
            
            // Status
            $table->enum('status', [
                'initiated', 'notice_period', 'handover_in_progress', 
                'exit_interview_scheduled', 'exit_interview_completed',
                'checklist_in_progress', 'checklist_completed', 
                'settlement_processed', 'completed'
            ])->default('initiated');
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['exit_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index(['exit_type', 'status']);
            $table->index('exit_date');
            $table->index('last_working_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exit_processes');
    }
};
