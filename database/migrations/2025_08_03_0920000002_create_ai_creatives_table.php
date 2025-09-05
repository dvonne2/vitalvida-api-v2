<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_creatives', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['text_ad', 'video_ad', 'image_ad', 'carousel_ad', 'story_ad']);
            $table->enum('platform', ['meta', 'tiktok', 'google', 'youtube', 'whatsapp', 'sms', 'email']);
            $table->text('prompt_used');
            $table->string('content_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->text('copy_text')->nullable();
            $table->decimal('performance_score', 5, 2)->default(0);
            $table->decimal('cpo', 8, 2)->nullable();
            $table->decimal('ctr', 6, 4)->nullable();
            $table->integer('orders_generated')->default(0);
            $table->decimal('spend', 10, 2)->default(0);
            $table->decimal('revenue', 10, 2)->default(0);
            $table->enum('status', ['pending', 'active', 'paused', 'completed', 'failed'])->default('pending');
            $table->decimal('ai_confidence_score', 3, 2)->default(0);
            $table->json('target_audience')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('ad_set_id')->nullable();
            $table->string('ad_id')->nullable();
            $table->unsignedBigInteger('parent_creative_id')->nullable();
            $table->string('variation_style')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['platform', 'status', 'performance_score']);
            $table->index(['cpo', 'orders_generated']);
            $table->index(['status', 'ai_confidence_score']);
            $table->index(['parent_creative_id']);
            
            // Foreign key
            $table->foreign('parent_creative_id')->references('id')->on('ai_creatives')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_creatives');
    }
}; 