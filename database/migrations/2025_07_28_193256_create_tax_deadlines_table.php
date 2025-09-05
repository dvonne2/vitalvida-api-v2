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
        Schema::create('tax_deadlines', function (Blueprint $table) {
            $table->id();
            $table->enum('tax_type', ['VAT', 'PAYE', 'WHT', 'CIT', 'EDT']);
            $table->string('filing_frequency'); // monthly, annual
            $table->integer('due_day'); // 21 for VAT, 10 for PAYE
            $table->string('due_month')->nullable(); // for annual taxes
            $table->text('description');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['tax_type', 'filing_frequency']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_deadlines');
    }
};
