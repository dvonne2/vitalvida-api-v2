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
        Schema::create('transfer_orders', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->string('from_location');
            $table->string('to_location');
            $table->unsignedBigInteger('delivery_agent_id')->nullable();
            $table->enum('status', ['pending', 'approved', 'completed', 'cancelled'])->default('pending');
            $table->date('transfer_date');
            $table->date('expected_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->integer('total_items')->default(0);
            $table->decimal('total_value', 15, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('cancelled_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'transfer_date']);
            $table->index(['from_location', 'to_location']);
            $table->index('transfer_number');
            $table->index('expected_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_orders');
    }
}; 