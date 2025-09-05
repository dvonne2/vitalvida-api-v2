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
        Schema::create('department_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->string('metric_name');
            $table->string('target_value');
            $table->string('actual_value');
            $table->enum('status', ['good', 'monitor', 'fix'])->default('monitor');
            $table->enum('trend', ['improving', 'stable', 'declining', 'concerning'])->default('stable');
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->date('measurement_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['department_id', 'metric_name']);
            $table->index('measurement_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_performances');
    }
};
