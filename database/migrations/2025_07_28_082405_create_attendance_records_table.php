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
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            
            // Record Information
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->date('date');
            $table->enum('day_type', ['workday', 'weekend', 'holiday', 'leave'])->default('workday');
            
            // Check-in/Check-out
            $table->time('scheduled_start_time')->nullable();
            $table->time('scheduled_end_time')->nullable();
            $table->time('actual_check_in_time')->nullable();
            $table->time('actual_check_out_time')->nullable();
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            
            // Time Calculations
            $table->integer('scheduled_hours')->default(8);
            $table->integer('actual_hours_worked')->nullable();
            $table->integer('overtime_hours')->default(0);
            $table->integer('break_minutes')->default(0);
            $table->integer('late_minutes')->default(0);
            $table->integer('early_departure_minutes')->default(0);
            
            // Status
            $table->enum('status', [
                'present', 'absent', 'late', 'early_departure', 'half_day', 
                'leave', 'sick_leave', 'vacation', 'holiday', 'remote_work'
            ])->default('present');
            
            // Location and Method
            $table->enum('work_location', ['office', 'remote', 'hybrid', 'field', 'client_site'])->default('office');
            $table->string('check_in_location')->nullable();
            $table->string('check_out_location')->nullable();
            $table->enum('check_in_method', ['biometric', 'mobile_app', 'web', 'manual', 'gps'])->nullable();
            $table->enum('check_out_method', ['biometric', 'mobile_app', 'web', 'manual', 'gps'])->nullable();
            
            // Verification
            $table->boolean('verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            
            // Notes and Exceptions
            $table->text('notes')->nullable();
            $table->text('exception_reason')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['employee_id', 'date']);
            $table->index(['date', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index('actual_check_in_time');
            $table->index('actual_check_out_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
