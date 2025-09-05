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
        // Update existing marketing_campaigns table for true omnipresence
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->json('customer_presence_channels')->nullable(); // specific channels for this campaign
            $table->decimal('relevancy_threshold', 5, 2)->default(7.5); // minimum relevancy score
            $table->json('trust_signals')->nullable(); // which trust signals to include
            $table->enum('intimacy_goal', ['awareness', 'consideration', 'conversion', 'loyalty'])->default('awareness');
            $table->json('unified_experience_rules')->nullable(); // cross-channel behavior rules
        });

        // Update existing marketing_customer_touchpoints table
        Schema::table('marketing_customer_touchpoints', function (Blueprint $table) {
            $table->decimal('relevancy_score', 5, 2)->nullable();
            $table->decimal('intimacy_impact', 5, 2)->nullable();
            $table->json('emotional_response')->nullable(); // customer's emotional reaction
            $table->string('trust_signal_used')->nullable();
            $table->uuid('unified_session_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'customer_presence_channels',
                'relevancy_threshold',
                'trust_signals',
                'intimacy_goal',
                'unified_experience_rules'
            ]);
        });

        Schema::table('marketing_customer_touchpoints', function (Blueprint $table) {
            $table->dropColumn([
                'relevancy_score',
                'intimacy_impact',
                'emotional_response',
                'trust_signal_used',
                'unified_session_id'
            ]);
        });
    }
};
