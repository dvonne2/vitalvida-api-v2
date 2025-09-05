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
        Schema::create('marketing_content_library', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('brand_id')->constrained('marketing_brands')->onDelete('cascade');
            $table->enum('content_type', ['text', 'image', 'video', 'audio', 'ai_generated']);
            $table->string('title');
            $table->text('content')->nullable(); // For text content
            $table->string('file_url')->nullable(); // For media files
            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->json('dimensions')->nullable(); // width, height for images/videos
            $table->integer('duration')->nullable(); // for videos/audio
            $table->json('variations')->nullable(); // Multiple content variations
            $table->json('sensory_tags')->nullable(); // Visual, auditory, emotional tags
            $table->decimal('performance_score', 5, 2)->default(0);
            $table->json('performance_metrics')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->text('generation_prompt')->nullable();
            $table->enum('status', ['draft', 'review', 'approved', 'published', 'archived'])->default('draft');
            $table->json('platform_optimized')->nullable(); // Which platforms it's optimized for
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('usage_rights')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index(['company_id', 'brand_id']);
            $table->index(['content_type', 'status']);
            $table->index(['ai_generated', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_content_library');
    }
};
