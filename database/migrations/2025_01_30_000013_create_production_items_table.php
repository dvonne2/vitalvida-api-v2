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
        Schema::create('production_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('production_id');
            $table->unsignedBigInteger('finished_item_id')->nullable();
            $table->unsignedBigInteger('raw_item_id')->nullable();
            $table->integer('quantity_used')->default(0);
            $table->integer('quantity_produced')->default(0);
            $table->decimal('unit_cost', 10, 2)->default(0.00);
            $table->decimal('total_cost', 12, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('production_id')->references('id')->on('productions')->onDelete('cascade');
            $table->foreign('finished_item_id')->references('id')->on('items')->onDelete('restrict');
            $table->foreign('raw_item_id')->references('id')->on('items')->onDelete('restrict');

            $table->index(['production_id', 'finished_item_id']);
            $table->index(['production_id', 'raw_item_id']);
            $table->index('finished_item_id');
            $table->index('raw_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_items');
    }
}; 