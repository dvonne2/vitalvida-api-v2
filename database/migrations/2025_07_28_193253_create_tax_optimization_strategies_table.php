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
        Schema::create('tax_optimization_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('strategy_name');
            $table->text('description');
            $table->decimal('potential_savings', 15, 2);
            $table->enum('implementation_status', ['available', 'implemented', 'not_applicable'])->default('available');
            $table->enum('difficulty_level', ['low', 'medium', 'high']);
            $table->date('deadline')->nullable();
            $table->timestamps();
            
            $table->index(['implementation_status', 'difficulty_level']);
            $table->index('potential_savings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_optimization_strategies');
    }
};
