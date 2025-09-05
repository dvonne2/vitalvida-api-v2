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
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->string('employee_name');
            $table->string('employee_role');
            $table->string('employee_department');
            $table->foreignId('payroll_id')->constrained('payrolls')->onDelete('cascade');
            $table->date('pay_period_month');
            $table->string('payslip_number')->unique();
            $table->decimal('base_salary', 12, 2);
            $table->decimal('prorated_salary', 12, 2);
            $table->decimal('total_bonuses', 12, 2)->default(0);
            $table->json('bonus_details')->nullable();
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->json('deduction_details')->nullable();
            $table->decimal('gross_pay', 12, 2);
            $table->decimal('net_pay', 12, 2);
            $table->integer('working_days');
            $table->integer('employee_working_days');
            $table->foreignId('generated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'pay_period_month']);
            $table->index(['payroll_id', 'employee_id']);
            $table->index('payslip_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
