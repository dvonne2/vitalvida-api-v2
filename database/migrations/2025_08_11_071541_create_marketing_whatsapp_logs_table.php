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
        Schema::create('marketing_whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->text('message');
            $table->enum('provider', ['wamation', 'ebulksms', 'whatsapp_business']);
            $table->enum('status', ['success', 'failed', 'pending']);
            $table->text('error_message')->nullable();
            $table->json('response_data')->nullable(); // Store provider response
            $table->foreignId('company_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained(); // Track who sent
            $table->uuid('campaign_id')->nullable(); // Link to campaign if applicable
            $table->timestamp('created_at');
            
            $table->index(['company_id', 'created_at']);
            $table->index(['provider', 'status']);
            $table->index(['phone', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_whatsapp_logs');
    }
};
