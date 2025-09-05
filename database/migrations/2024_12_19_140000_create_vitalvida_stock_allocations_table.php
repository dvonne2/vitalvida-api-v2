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
        Schema::create('vitalvida_stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('agent_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_value', 12, 2);
            $table->string('allocation_type')->default('manual'); // manual, smart, predictive
            $table->string('status')->default('allocated'); // allocated, delivered, returned
            $table->text('allocation_reason')->nullable();
            $table->json('allocation_metadata')->nullable(); // Smart allocation scores, predictions
            $table->timestamp('allocated_at');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedBigInteger('allocated_by')->nullable(); // User ID who made allocation
            $table->string('allocation_source')->default('manual'); // manual, smart_allocation, predictive_restocking
            $table->decimal('allocation_score', 5, 2)->nullable(); // Smart allocation confidence score
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('vitalvida_products')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('vitalvida_delivery_agents')->onDelete('cascade');
            $table->foreign('allocated_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['product_id', 'agent_id']);
            $table->index(['allocated_at']);
            $table->index(['status']);
            $table->index(['allocation_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_stock_allocations');
    }
};
