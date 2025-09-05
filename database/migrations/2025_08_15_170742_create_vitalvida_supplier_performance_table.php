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
        Schema::create('vitalvida_supplier_performance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('vitalvida_suppliers')->onDelete('cascade');
            $table->date('performance_date');
            $table->decimal('delivery_rating', 3, 2)->default(0.00); // On-time delivery rating
            $table->decimal('quality_rating', 3, 2)->default(0.00); // Product quality rating
            $table->decimal('service_rating', 3, 2)->default(0.00); // Customer service rating
            $table->integer('orders_completed')->default(0);
            $table->integer('orders_delayed')->default(0);
            $table->decimal('order_value', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_supplier_performance');
    }
};
