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
        Schema::create('logistics_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->date('month');
            $table->integer('deliveries_completed')->default(0);
            $table->integer('deliveries_on_time')->default(0);
            $table->decimal('delivery_efficiency', 5, 2)->default(0);
            $table->decimal('cost_savings', 12, 2)->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->decimal('customer_satisfaction', 5, 2)->default(0);
            $table->decimal('error_rate', 5, 2)->default(0);
            $table->decimal('fuel_efficiency', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'month']);
            $table->index(['month', 'delivery_efficiency']);
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistics_metrics');
    }
};
