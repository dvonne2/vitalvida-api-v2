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
        Schema::create('kpi_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Dispatch Accuracy Rate, Delivery Chain Match Rate, etc.
            $table->decimal('current_value', 5, 2);
            $table->decimal('target_value', 5, 2)->nullable();
            $table->string('unit', 20)->nullable(); // %, mins, etc.
            $table->enum('status', ['poor', 'good', 'excellent'])->default('good');
            $table->string('period', 20)->default('daily'); // daily, weekly, monthly
            $table->date('recorded_date')->default(now());
            $table->timestamps();
            
            $table->index(['name', 'period', 'recorded_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_metrics');
    }
};
