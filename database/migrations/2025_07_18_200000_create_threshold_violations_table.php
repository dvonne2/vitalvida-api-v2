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
        Schema::create('threshold_violations', function (Blueprint $table) {
            $table->id();
            
            // Violation details
            $table->unsignedBigInteger('expense_id')->nullable(); // Link to expense request
            $table->decimal('amount', 15, 2); // Original expense amount
            $table->string('category'); // logistics, expenses, generator_fuel, etc.
            $table->string('subcategory')->nullable(); // cost_per_unit, storekeeper_fee, etc.
            $table->string('violation_type'); // fixed_limit_exceeded, dual_approval_required, etc.
            $table->decimal('excess_amount', 15, 2); // Amount exceeding threshold
            $table->decimal('threshold_limit', 15, 2)->nullable(); // Threshold that was exceeded
            $table->json('context')->nullable(); // Additional context data
            
            // Status tracking
            $table->enum('status', [
                'pending_approval',
                'approved',
                'rejected',
                'unauthorized_payment',
                'timeout_rejected'
            ])->default('pending_approval');
            
            // Approval tracking
            $table->unsignedBigInteger('created_by')->nullable(); // User who created the violation
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            // Salary deduction tracking
            $table->unsignedBigInteger('salary_deduction_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['category', 'subcategory']);
            $table->index('created_by');
            $table->index('expense_id');
            $table->index('salary_deduction_id');
            
            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('salary_deduction_id')->references('id')->on('salary_deductions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threshold_violations');
    }
}; 