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
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_count_id');
            $table->unsignedBigInteger('item_id');
            $table->integer('expected_quantity');
            $table->integer('actual_quantity')->nullable();
            $table->integer('variance')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('inventory_count_id')->references('id')->on('inventory_counts')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('restrict');

            $table->index(['inventory_count_id', 'item_id']);
            $table->index('item_id');
            $table->index('variance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
    }
}; 