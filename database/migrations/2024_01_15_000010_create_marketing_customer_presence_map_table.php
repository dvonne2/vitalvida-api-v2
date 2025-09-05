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
        Schema::create('marketing_customer_presence_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('channel'); // whatsapp, facebook, instagram, email, etc.
            $table->decimal('engagement_score', 5, 2)->default(0); // How engaged they are on this channel
            $table->json('frequency_hours')->nullable(); // When they're most active
            $table->json('behavior_patterns')->nullable(); // How they behave on this channel
            $table->decimal('conversion_rate', 5, 4)->default(0); // Conversion rate on this channel
            $table->timestamp('last_active')->nullable();
            $table->json('real_time_activity')->nullable(); // Live activity tracking
            $table->decimal('emotional_engagement', 5, 2)->nullable(); // Emotional connection score
            $table->json('contextual_preferences')->nullable(); // Context-based channel preferences
            $table->timestamp('last_meaningful_interaction')->nullable(); // Last significant engagement
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['customer_id', 'channel', 'company_id']);
            $table->index(['engagement_score', 'conversion_rate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_customer_presence_map');
    }
};
