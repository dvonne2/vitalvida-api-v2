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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('base_salary', 15, 2)->nullable()->after('email');
            $table->decimal('annual_salary', 15, 2)->nullable()->after('base_salary');
            $table->string('employee_id')->nullable()->after('annual_salary');
            $table->decimal('life_insurance_premium', 10, 2)->default(0)->after('employee_id');
            $table->decimal('medical_expenses', 10, 2)->default(0)->after('life_insurance_premium');
            $table->json('bank_details')->nullable()->after('medical_expenses');
            $table->date('employment_start_date')->nullable()->after('bank_details');
            
            // Indexes
            $table->index('employee_id');
            $table->index('employment_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['employee_id']);
            $table->dropIndex(['employment_start_date']);
            $table->dropColumn([
                'base_salary',
                'annual_salary', 
                'employee_id',
                'life_insurance_premium',
                'medical_expenses',
                'bank_details',
                'employment_start_date'
            ]);
        });
    }
}; 