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
        Schema::create('ucx_customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->json('unified_profile'); // Single source of truth for customer
            $table->json('real_time_context'); // Current customer context across all channels
            $table->json('behavior_patterns'); // Live behavior analysis
            $table->json('preferences_learned'); // Dynamically learned preferences
            $table->json('emotional_state'); // Current emotional context
            $table->timestamp('last_interaction'); // Most recent touchpoint
            $table->string('current_journey_stage'); // Where they are right now
            $table->json('next_best_actions'); // AI-recommended next steps
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['customer_id', 'company_id']);
            $table->index(['last_interaction', 'current_journey_stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ucx_customer_profiles');
    }
};
