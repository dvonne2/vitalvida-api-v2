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
            $table->uuid('brand_id');
            $table->enum('content_type', ['image', 'video', 'text', 'audio', 'ai_generated']);
            $table->string('title');
            $table->string('file_url')->nullable();
            $table->json('variations')->nullable();
            $table->json('sensory_tags')->nullable();
            $table->decimal('performance_score', 3, 2)->nullable();
            $table->integer('usage_count')->default(0);
            $table->json('generation_prompt')->nullable(); // Store AI generation prompt
            $table->foreignId('company_id')->constrained(); // ERP integration
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            
            $table->foreign('brand_id')->references('id')->on('marketing_brands');
            $table->index(['company_id', 'content_type']);
            $table->index(['brand_id', 'content_type']);
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
