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
        Schema::create('strike_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_agent_id');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->string('source')->default('payout_compliance');
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->unsignedBigInteger('issued_by');
            $table->unsignedBigInteger('payout_id')->nullable();
            $table->timestamps();

            $table->index('delivery_agent_id');
            $table->index(['delivery_agent_id', 'created_at']);
            $table->index('severity');
            
            $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents')->onDelete('cascade');
            $table->foreign('issued_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('payout_id')->references('id')->on('payouts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strike_logs');
    }
};
