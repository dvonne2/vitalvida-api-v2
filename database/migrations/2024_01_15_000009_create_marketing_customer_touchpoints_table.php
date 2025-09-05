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
        Schema::create('marketing_customer_touchpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('brand_id')->constrained('marketing_brands')->onDelete('cascade');
            $table->foreignUuid('campaign_id')->nullable()->constrained('marketing_campaigns')->onDelete('set null');
            $table->foreignUuid('content_id')->nullable()->constrained('marketing_content_library')->onDelete('set null');
            $table->string('channel'); // whatsapp, email, facebook, instagram, etc.
            $table->string('touchpoint_type'); // message_sent, email_opened, ad_clicked, etc.
            $table->enum('interaction_type', ['sent', 'delivered', 'opened', 'clicked', 'converted', 'bounced']);
            $table->json('touchpoint_data')->nullable(); // Message content, metadata
            $table->json('emotional_response')->nullable(); // Customer's emotional reaction
            $table->decimal('engagement_score', 5, 2)->nullable();
            $table->uuid('ucx_session_id')->nullable(); // Link to UCX session
            $table->json('emotional_context')->nullable(); // Customer's emotional state
            $table->json('carried_context')->nullable(); // Context from previous touchpoints
            $table->boolean('context_continuity_maintained')->default(false); // Did context flow
            $table->decimal('emotional_impact_score', 5, 2)->nullable(); // Emotional impact
            $table->json('personalization_applied')->nullable(); // Real-time personalization used
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['customer_id', 'channel']);
            $table->index(['campaign_id', 'interaction_type']);
            $table->index(['ucx_session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_customer_touchpoints');
    }
};
