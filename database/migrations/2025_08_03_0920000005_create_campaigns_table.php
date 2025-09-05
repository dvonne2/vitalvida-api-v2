<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('campaign_type');
            $table->enum('platform', ['meta', 'tiktok', 'google', 'youtube', 'whatsapp', 'sms', 'email']);
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->decimal('budget', 12, 2)->default(0);
            $table->decimal('spent', 12, 2)->default(0);
            $table->json('target_audience')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->json('performance_metrics')->nullable();
            $table->boolean('ai_optimization_enabled')->default(false);
            $table->boolean('auto_scale_enabled')->default(false);
            $table->decimal('target_cpo', 8, 2)->nullable();
            $table->decimal('target_ctr', 6, 4)->nullable();
            $table->decimal('actual_cpo', 8, 2)->nullable();
            $table->decimal('actual_ctr', 6, 4)->nullable();
            $table->integer('orders_generated')->default(0);
            $table->decimal('revenue_generated', 12, 2)->default(0);
            $table->decimal('roi', 8, 2)->default(0);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['platform', 'status']);
            $table->index(['campaign_type', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index(['actual_cpo', 'actual_ctr']);
            $table->index(['roi', 'orders_generated']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('campaigns');
    }
}; 