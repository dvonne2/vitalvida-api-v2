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
        Schema::create('financial_statements', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['profit_loss', 'balance_sheet', 'cash_flow'])->default('profit_loss');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_name'); // e.g., "Q1 2024", "January 2024"
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('cost_of_goods_sold', 15, 2)->default(0);
            $table->decimal('gross_profit', 15, 2)->default(0);
            $table->decimal('operating_expenses', 15, 2)->default(0);
            $table->decimal('operating_income', 15, 2)->default(0);
            $table->decimal('net_income', 15, 2)->default(0);
            $table->decimal('total_assets', 15, 2)->default(0);
            $table->decimal('total_liabilities', 15, 2)->default(0);
            $table->decimal('total_equity', 15, 2)->default(0);
            $table->decimal('operating_cash_flow', 15, 2)->default(0);
            $table->decimal('investing_cash_flow', 15, 2)->default(0);
            $table->decimal('financing_cash_flow', 15, 2)->default(0);
            $table->decimal('net_cash_flow', 15, 2)->default(0);
            $table->decimal('cash_balance', 15, 2)->default(0);
            $table->json('additional_metrics')->nullable(); // Store other financial metrics
            $table->json('breakdown')->nullable(); // Detailed breakdown of line items
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'reviewed', 'approved', 'published'])->default('draft');
            $table->foreignId('prepared_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['type', 'period_start', 'period_end']);
            $table->index('status');
            $table->index('period_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_statements');
    }
};
