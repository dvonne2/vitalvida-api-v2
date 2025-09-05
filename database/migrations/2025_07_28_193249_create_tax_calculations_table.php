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
        Schema::create('tax_calculations', function (Blueprint $table) {
            $table->id();
            $table->enum('tax_type', ['VAT', 'PAYE', 'CIT', 'EDT', 'WHT']);
            $table->string('period'); // 2025-07 for monthly, 2025 for annual
            $table->decimal('taxable_amount', 15, 2);
            $table->decimal('tax_rate', 5, 2); // 7.5%, 30%, etc.
            $table->decimal('tax_amount', 15, 2);
            $table->enum('status', ['calculated', 'filed', 'paid', 'overdue'])->default('calculated');
            $table->date('due_date');
            $table->date('filed_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['tax_type', 'period']);
            $table->index(['due_date', 'status']);
            $table->index(['tax_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_calculations');
    }
};
