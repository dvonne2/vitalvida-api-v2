<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agent_activity_logs')) {
            Schema::create('agent_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_agent_id');
                $table->enum('activity_type', [
                    'login', 'logout', 'pickup', 'delivery', 'location_update',
                    'status_change', 'order_acceptance', 'order_rejection'
                ]);
                
                $table->string('description');
                $table->json('activity_data')->nullable();
                $table->string('ip_address')->nullable();
                $table->unsignedBigInteger('related_order_id')->nullable();
                
                $table->timestamps();
                
                $table->index(['delivery_agent_id', 'created_at']);
                $table->index('activity_type');
                $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_activity_logs');
    }
};
