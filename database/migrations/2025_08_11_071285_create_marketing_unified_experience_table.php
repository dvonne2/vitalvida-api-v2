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
        Schema::create('marketing_unified_experience', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->uuid('session_id'); // track cross-channel sessions
            $table->json('context_data')->nullable(); // what customer is doing
            $table->json('current_intent')->nullable(); // what they want
            $table->string('current_channel');
            $table->string('entry_channel'); // how they started journey
            $table->json('channel_progression')->nullable(); // their path
            $table->timestamp('session_start');
            $table->timestamp('session_end')->nullable();
            $table->foreignId('company_id')->constrained();
            $table->timestamps();
            
            $table->index(['customer_id', 'session_start']);
            $table->index(['company_id', 'session_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_unified_experience');
    }
};
