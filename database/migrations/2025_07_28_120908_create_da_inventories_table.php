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
        Schema::create('da_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('da_id')->constrained('delivery_agents')->onDelete('cascade');
            $table->enum('product_type', ['shampoo', 'pomade', 'conditioner']);
            $table->integer('quantity')->default(0);
            $table->timestamp('last_updated')->nullable();
            $table->integer('days_stagnant')->default(0);
            $table->integer('min_stock_level')->default(3);
            $table->integer('max_stock_level')->default(20);
            $table->integer('reorder_point')->default(5);
            $table->timestamp('last_restock_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['da_id', 'product_type']);
            $table->index('quantity');
            $table->index('days_stagnant');
            $table->index('last_updated');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('da_inventories');
    }
};
