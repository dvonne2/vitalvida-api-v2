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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('department'); // Marketing, Operations, etc.
            $table->string('fiscal_year', 4);
            $table->string('month', 7); // 2025-07
            $table->decimal('budget_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'locked'])->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('notes')->nullable();
            $table->json('budget_categories')->nullable(); // Detailed breakdown
            $table->decimal('variance_percentage', 5, 2)->default(0);
            $table->enum('variance_status', ['under_budget', 'on_budget', 'over_budget'])->default('on_budget');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->unique(['department', 'fiscal_year', 'month']);
            $table->index(['department', 'status']);
            $table->index(['fiscal_year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
