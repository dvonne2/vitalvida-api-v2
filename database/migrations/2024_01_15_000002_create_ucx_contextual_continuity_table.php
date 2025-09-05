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
        Schema::create('ucx_contextual_continuity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->uuid('session_id'); // Cross-channel session tracking
            $table->string('entry_channel'); // How they started
            $table->string('current_channel'); // Where they are now
            $table->json('channel_progression'); // Their journey path
            $table->json('context_data'); // What they were doing
            $table->json('carried_context'); // Context that follows them
            $table->json('personalization_applied'); // How we personalized experience
            $table->timestamp('session_start');
            $table->timestamp('last_activity');
            $table->boolean('session_active')->default(true);
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['customer_id', 'session_active']);
            $table->index(['session_id', 'last_activity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ucx_contextual_continuity');
    }
};
