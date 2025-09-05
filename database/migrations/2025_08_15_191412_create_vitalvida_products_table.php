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
        Schema::create('vitalvida_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->integer('stock_level')->default(0);
            $table->integer('min_stock')->default(10);
            $table->integer('max_stock')->default(1000);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->enum('status', ['In Stock', 'Low Stock', 'Out of Stock', 'Discontinued'])->default('In Stock');
            $table->foreignId('supplier_id')->nullable()->constrained('vitalvida_suppliers');
            $table->foreignId('agent_id')->nullable()->constrained('vitalvida_delivery_agents');
            $table->string('category')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_products');
    }
};
