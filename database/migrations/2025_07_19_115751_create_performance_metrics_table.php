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
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->date('month');
            $table->decimal('individual_score', 5, 2)->default(0);
            $table->decimal('team_score', 5, 2)->default(0);
            $table->integer('individual_targets_met')->default(0);
            $table->integer('team_targets_met')->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->decimal('attendance_score', 5, 2)->default(0);
            $table->decimal('customer_satisfaction', 5, 2)->default(0);
            $table->integer('innovation_points')->default(0);
            $table->decimal('overall_rating', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'month']);
            $table->index(['month', 'overall_rating']);
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
};
