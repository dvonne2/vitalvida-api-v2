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
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            
            // Request Information
            $table->string('request_id', 20)->unique(); // LVR001, LVR002, etc.
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            
            // Leave Details
            $table->enum('leave_type', [
                'annual', 'sick', 'personal', 'maternity', 'paternity', 
                'bereavement', 'jury_duty', 'military', 'unpaid', 'other'
            ])->default('annual');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            $table->integer('working_days');
            $table->text('reason')->nullable();
            $table->text('additional_notes')->nullable();
            
            // Status and Approval
            $table->enum('status', [
                'pending', 'approved', 'rejected', 'cancelled', 'in_progress', 'completed'
            ])->default('pending');
            $table->enum('approval_level', ['manager', 'hr', 'director', 'ceo'])->default('manager');
            
            // Approval Chain
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Leave Balance
            $table->integer('leave_balance_before')->nullable();
            $table->integer('leave_balance_after')->nullable();
            $table->integer('leave_balance_used')->nullable();
            
            // Emergency Contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->text('emergency_contact_address')->nullable();
            
            // Handover Information
            $table->json('handover_tasks')->nullable();
            $table->foreignId('handover_to')->nullable()->constrained('employees')->onDelete('set null');
            $table->text('handover_notes')->nullable();
            $table->boolean('handover_completed')->default(false);
            
            // Return Information
            $table->date('actual_return_date')->nullable();
            $table->text('return_notes')->nullable();
            $table->boolean('return_confirmed')->default(false);
            $table->timestamp('return_confirmed_at')->nullable();
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['request_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index(['leave_type', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
