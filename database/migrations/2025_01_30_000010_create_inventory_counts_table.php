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
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->string('count_number', 50)->unique();
            $table->unsignedBigInteger('delivery_agent_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->date('date');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'approved'])->default('pending');
            $table->enum('type', ['full', 'partial'])->default('full');
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents')->onDelete('set null');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'date']);
            $table->index(['delivery_agent_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('count_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_counts');
    }
}; 