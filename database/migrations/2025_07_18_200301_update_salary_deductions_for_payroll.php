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
        Schema::table('salary_deductions', function (Blueprint $table) {
            $table->foreignId('processed_payroll_id')->nullable()->after('processed_by')->constrained('payrolls')->onDelete('set null');
            $table->index('processed_payroll_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_deductions', function (Blueprint $table) {
            $table->dropForeign(['processed_payroll_id']);
            $table->dropIndex(['processed_payroll_id']);
            $table->dropColumn('processed_payroll_id');
        });
    }
}; 