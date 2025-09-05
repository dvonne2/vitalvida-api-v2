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
        Schema::table('bonus_logs', function (Blueprint $table) {
            $table->foreignId('processed_payroll_id')->nullable()->after('payment_reference')->constrained('payrolls')->onDelete('set null');
            $table->text('approval_notes')->nullable()->after('processed_payroll_id');
            $table->index('processed_payroll_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bonus_logs', function (Blueprint $table) {
            $table->dropForeign(['processed_payroll_id']);
            $table->dropIndex(['processed_payroll_id']);
            $table->dropColumn(['processed_payroll_id', 'approval_notes']);
        });
    }
}; 