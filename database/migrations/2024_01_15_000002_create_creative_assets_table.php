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
        Schema::create('creative_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['image', 'video', 'copy', 'audio'])->default('copy');
            $table->longText('content')->nullable(); // For copy content
            $table->string('file_path')->nullable(); // For file uploads
            $table->bigInteger('file_size')->nullable(); // In bytes
            $table->string('mime_type')->nullable();
            $table->json('dimensions')->nullable(); // For images/videos: width, height
            $table->integer('duration')->nullable(); // For videos: duration in seconds
            $table->json('tags')->nullable();
            $table->enum('status', [
                'draft', 
                'review', 
                'approved', 
                'published'
            ])->default('draft');
            $table->enum('platform', [
                'facebook', 
                'instagram', 
                'tiktok', 
                'google', 
                'general'
            ])->default('general');
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->json('performance_metrics')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->text('generation_prompt')->nullable();
            $table->decimal('cost', 10, 2)->default(0); // Cost to generate/purchase
            $table->text('usage_rights')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'status']);
            $table->index(['platform', 'status']);
            $table->index(['campaign_id', 'status']);
            $table->index(['created_by', 'status']);
            $table->index(['ai_generated', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('creative_assets');
    }
};
