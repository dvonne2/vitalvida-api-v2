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
        Schema::create('ucx_emotional_journey_mapping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->uuid('journey_id'); // Unique journey instance
            $table->string('journey_stage'); // awareness, consideration, decision, etc.
            $table->json('emotional_markers'); // Fear, excitement, confusion, trust, etc.
            $table->decimal('emotional_intensity', 3, 2); // How strong the emotion
            $table->json('triggers_identified'); // What caused the emotion
            $table->json('sentiment_analysis'); // Positive, negative, neutral with scores
            $table->string('channel_when_measured'); // Where emotion was detected
            $table->json('response_strategy'); // How we responded to this emotion
            $table->timestamp('measured_at');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['customer_id', 'journey_stage']);
            $table->index(['emotional_intensity', 'measured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ucx_emotional_journey_mapping');
    }
};
