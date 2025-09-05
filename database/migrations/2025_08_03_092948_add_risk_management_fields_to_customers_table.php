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
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('abandoned_orders')->default(0)->after('total_orders');
            $table->integer('completed_orders')->default(0)->after('abandoned_orders');
            $table->enum('risk_level', ['TRUSTED', 'RISK1', 'RISK2', 'RISK3'])->default('TRUSTED')->after('completed_orders');
            $table->integer('risk_score')->default(0)->after('risk_level');
            $table->boolean('requires_prepayment')->default(false)->after('risk_score');
            $table->integer('recovery_orders')->default(0)->after('requires_prepayment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'abandoned_orders',
                'completed_orders', 
                'risk_level',
                'risk_score',
                'requires_prepayment',
                'recovery_orders'
            ]);
        });
    }
};
