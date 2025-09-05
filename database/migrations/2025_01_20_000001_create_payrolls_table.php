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
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            
            // Employee and period information
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_description')->nullable(); // "January 2025", etc.
            
            // Salary components
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('total_bonuses', 15, 2)->default(0);
            $table->decimal('gross_pay', 15, 2); // base_salary + bonuses
            
            // Deductions
            $table->decimal('paye_tax', 15, 2)->default(0);
            $table->decimal('pension_employee', 15, 2)->default(0);
            $table->decimal('nhf_employee', 15, 2)->default(0);
            $table->decimal('salary_deductions', 15, 2)->default(0); // From Phase 4
            $table->decimal('other_deductions', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2);
            
            // Employer contributions (for record keeping)
            $table->decimal('pension_employer', 15, 2)->default(0);
            $table->decimal('nhf_employer', 15, 2)->default(0);
            $table->decimal('nsitf_employer', 15, 2)->default(0);
            $table->decimal('total_employer_contributions', 15, 2)->default(0);
            
            // Net pay
            $table->decimal('net_pay', 15, 2);
            
            // Processing information
            $table->enum('status', [
                'draft',
                'processed',
                'paid',
                'cancelled'
            ])->default('draft');
            
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Payment details
            $table->string('payment_reference')->nullable();
            $table->string('payment_method')->nullable(); // bank_transfer, cash, etc.
            $table->json('payment_details')->nullable(); // Bank details, etc.
            
            // Detailed breakdown
            $table->json('breakdown')->nullable(); // Detailed calculation breakdown
            $table->json('tax_calculation')->nullable(); // Tax calculation details
            
            // Approval workflow
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            
            // Audit fields
            $table->string('currency', 3)->default('NGN');
            $table->json('exchange_rates')->nullable(); // If multi-currency support needed
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['employee_id', 'period_start', 'period_end']);
            $table->index(['status', 'processed_at']);
            $table->index(['period_start', 'period_end']);
            $table->index('processed_by');
            $table->index('approved_by');
            
            // Unique constraint to prevent duplicate payroll for same period
            $table->unique(['employee_id', 'period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
}; 