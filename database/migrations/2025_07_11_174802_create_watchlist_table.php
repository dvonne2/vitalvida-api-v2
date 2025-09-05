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
        Schema::create('watchlist', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_agent_id');
            $table->text('reason');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('escalated_at');
            $table->boolean('is_active')->default(true);
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('delivery_agent_id');
            $table->index(['is_active', 'escalated_at']);
            
            $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watchlist');
    }
};
