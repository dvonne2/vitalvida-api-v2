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
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('delivery_agent_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->enum('adjustment_type', ['damage', 'loss', 'found', 'theft', 'expiry', 'quality_control', 'inventory_count', 'system_adjustment'])->default('system_adjustment');
            $table->integer('quantity'); // Can be positive (increase) or negative (decrease)
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->date('date');
            $table->string('reference_number', 50)->unique();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('item_id')->references('id')->on('items')->onDelete('restrict');
            $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents')->onDelete('set null');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'date']);
            $table->index(['item_id', 'date']);
            $table->index(['delivery_agent_id', 'status']);
            $table->index('adjustment_type');
            $table->index('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
}; 