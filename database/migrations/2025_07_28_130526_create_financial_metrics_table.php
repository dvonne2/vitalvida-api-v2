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
        Schema::create('financial_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('revenue', 12, 2)->default(0);
            $table->decimal('costs', 12, 2)->default(0);
            $table->decimal('profit', 12, 2)->default(0);
            $table->decimal('margin', 5, 2)->default(0); // percentage
            $table->integer('orders_count')->default(0);
            $table->integer('delivered_orders')->default(0);
            $table->integer('ghosted_orders')->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            $table->json('product_line_data')->nullable();
            $table->json('da_performance_data')->nullable();
            $table->json('state_performance_data')->nullable();
            $table->timestamps();
            
            $table->index('date');
            $table->unique('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_metrics');
    }
};
