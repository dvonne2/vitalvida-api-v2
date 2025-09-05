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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            
            // Product Information
            $table->unsignedBigInteger('product_id')->nullable();
            
            // Movement Classification
            $table->enum('movement_type', [
                'inbound',      // Factory → Warehouse
                'outbound',     // Warehouse → Factory (returns)
                'transfer',     // Warehouse → DA, DA → DA (via IM)
                'return',       // DA → Warehouse
                'adjustment'    // Manual stock corrections
            ]);
            
            // Source & Destination (Polymorphic)
            $table->string('source_type')->nullable(); // 'factory', 'warehouse', 'delivery_agent'
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('destination_type')->nullable(); // 'factory', 'warehouse', 'delivery_agent'
            $table->unsignedBigInteger('destination_id')->nullable();
            
            // Quantity & Reference
            $table->integer('quantity');
            $table->string('reference_type')->nullable(); // 'purchase_order', 'da_return', 'transfer', etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Audit Information
            $table->string('performed_by'); // Who performed the action
            $table->text('notes')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['product_id', 'created_at']);
            $table->index(['movement_type', 'created_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['destination_type', 'destination_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('performed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
