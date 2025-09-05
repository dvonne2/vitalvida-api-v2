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
        Schema::create('company_valuations', function (Blueprint $table) {
            $table->id();
            $table->date('valuation_date');
            $table->decimal('total_company_value', 15, 2); // Total company valuation
            $table->decimal('equity_value', 15, 2); // Equity portion
            $table->decimal('debt_value', 15, 2)->default(0); // Debt portion
            $table->decimal('cash_value', 15, 2)->default(0); // Cash and equivalents
            $table->decimal('revenue_multiple', 8, 2)->nullable(); // Revenue multiple used
            $table->decimal('ebitda_multiple', 8, 2)->nullable(); // EBITDA multiple used
            $table->decimal('discount_rate', 5, 2)->nullable(); // Discount rate used
            $table->decimal('growth_rate', 5, 2)->nullable(); // Growth rate assumption
            $table->json('equity_distribution')->nullable(); // Breakdown of equity ownership
            $table->json('valuation_methods')->nullable(); // Methods used (DCF, Comparable, etc.)
            $table->text('assumptions')->nullable(); // Key assumptions made
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'reviewed', 'approved', 'published'])->default('draft');
            $table->foreignId('prepared_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('valuation_date');
            $table->index('status');
            $table->index('total_company_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_valuations');
    }
};
