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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('employee_id', 20)->unique(); // EMP001, EMP002, etc.
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 100)->default('Nigeria');
            
            // Employment Information
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            $table->foreignId('position_id')->constrained('positions')->onDelete('restrict');
            $table->date('hire_date');
            $table->date('probation_end_date')->nullable();
            $table->date('confirmation_date')->nullable();
            $table->enum('employment_status', ['active', 'probation', 'suspended', 'terminated', 'resigned'])->default('active');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time');
            
            // Salary Information
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('current_salary', 15, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name', 100)->nullable();
            
            // Emergency Contact
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 20)->nullable();
            $table->string('emergency_contact_relationship', 50)->nullable();
            
            // Performance & Training
            $table->decimal('performance_rating', 3, 2)->nullable(); // 0.00 to 5.00
            $table->date('last_performance_review')->nullable();
            $table->date('next_performance_review')->nullable();
            $table->decimal('training_completion_rate', 5, 2)->default(0); // 0.00 to 100.00
            
            // Attendance
            $table->decimal('attendance_rate', 5, 2)->default(100); // 0.00 to 100.00
            $table->integer('days_absent_this_month')->default(0);
            $table->integer('days_late_this_month')->default(0);
            
            // System Integration
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('permissions')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            
            // Metadata
            $table->text('notes')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'employment_status']);
            $table->index(['department_id', 'employment_status']);
            $table->index(['position_id', 'employment_status']);
            $table->index('hire_date');
            $table->index('performance_rating');
            $table->index('attendance_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
