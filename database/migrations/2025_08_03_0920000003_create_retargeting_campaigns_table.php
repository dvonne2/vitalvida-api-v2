<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('retargeting_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->enum('platform', ['meta', 'tiktok', 'google', 'youtube', 'whatsapp', 'sms', 'email']);
            $table->string('campaign_type');
            $table->enum('status', ['scheduled', 'sent', 'delivered', 'failed'])->default('scheduled');
            $table->json('message_content');
            $table->json('target_audience')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('response_received')->default(false);
            $table->boolean('conversion_achieved')->default(false);
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('revenue_generated', 10, 2)->default(0);
            $table->json('performance_metrics')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['customer_id', 'campaign_type']);
            $table->index(['platform', 'status']);
            $table->index(['scheduled_at', 'status']);
            $table->index(['conversion_achieved', 'cost']);
            
            // Foreign key
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('retargeting_campaigns');
    }
}; 