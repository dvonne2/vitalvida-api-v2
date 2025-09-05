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
        Schema::create('marketing_trust_signals', function (Blueprint $table) {
            $table->id();
            $table->uuid('brand_id');
            $table->enum('signal_type', ['authority', 'social_proof', 'familiarity', 'demonstration']);
            $table->string('signal_source'); // testimonial, review, certification, etc.
            $table->text('signal_content');
            $table->string('source_url')->nullable();
            $table->decimal('credibility_score', 5, 2);
            $table->json('display_channels')->nullable(); // where to show this signal
            $table->boolean('is_active')->default(true);
            $table->foreignId('company_id')->constrained();
            $table->timestamps();
            
            $table->foreign('brand_id')->references('id')->on('marketing_brands');
            $table->index(['signal_type', 'credibility_score']);
            $table->index(['company_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_trust_signals');
    }
};
