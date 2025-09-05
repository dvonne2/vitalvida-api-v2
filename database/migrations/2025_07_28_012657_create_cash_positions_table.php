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
        Schema::create('cash_positions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);
            $table->decimal('total_inflow', 15, 2)->default(0);
            $table->decimal('total_outflow', 15, 2)->default(0);
            $table->decimal('net_cash_flow', 15, 2)->default(0);
            $table->decimal('cash_on_hand', 15, 2)->default(0);
            $table->decimal('bank_balance', 15, 2)->default(0);
            $table->decimal('pending_receivables', 15, 2)->default(0);
            $table->decimal('pending_payables', 15, 2)->default(0);
            $table->decimal('cash_reserves', 15, 2)->default(0);
            $table->decimal('operating_cash', 15, 2)->default(0);
            $table->decimal('investment_cash', 15, 2)->default(0);
            $table->decimal('financing_cash', 15, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('date');
            $table->unique('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_positions');
    }
};
