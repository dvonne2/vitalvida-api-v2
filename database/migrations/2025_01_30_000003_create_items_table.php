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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('sku', 50)->unique();
            $table->text('description')->nullable();
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->decimal('cost_price', 10, 2)->default(0.00);
            $table->decimal('selling_price', 10, 2)->default(0.00);
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_level')->default(10);
            $table->integer('max_stock')->nullable();
            $table->integer('min_stock')->default(0);
            $table->string('unit_of_measure', 20)->default('pcs');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('barcode', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_tracked')->default(true);
            $table->string('location')->nullable();
            $table->string('shelf_location')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('manufacturing_date')->nullable();
            $table->integer('warranty_period')->nullable(); // in days
            $table->decimal('weight', 8, 3)->nullable(); // in kg
            $table->json('dimensions')->nullable(); // length, width, height
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('margin_percentage', 5, 2)->default(0.00);
            $table->date('last_purchase_date')->nullable();
            $table->date('last_sale_date')->nullable();
            $table->integer('total_purchased')->default(0);
            $table->integer('total_sold')->default(0);
            $table->integer('total_returned')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            
            $table->index(['is_active', 'stock_quantity']);
            $table->index(['category_id', 'is_active']);
            $table->index(['supplier_id', 'is_active']);
            $table->index('sku');
            $table->index('location');
            $table->index('expiry_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
}; 