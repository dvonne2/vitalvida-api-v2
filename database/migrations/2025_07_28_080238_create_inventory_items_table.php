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
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->string('category', 100)->nullable();
            $table->string('brand', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('reorder_level')->default(10);
            $table->integer('max_stock')->nullable();
            $table->string('location', 100)->nullable();
            $table->string('supplier', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('barcode', 100)->nullable();
            $table->decimal('cost_price', 10, 2)->default(0.00);
            $table->decimal('selling_price', 10, 2)->default(0.00);
            $table->integer('sold_quantity')->default(0);
            $table->integer('returned_quantity')->default(0);
            $table->timestamp('last_restocked_at')->nullable();
            $table->timestamp('last_sold_at')->nullable();
            $table->timestamps();
            
            $table->index(['is_active', 'quantity']);
            $table->index('category');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
