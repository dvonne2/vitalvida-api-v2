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
            $table->json('provider_response')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignUuid('campaign_id')->nullable()->constrained('marketing_campaigns')->onDelete('set null');
            $table->decimal('cost', 8, 4)->default(0);
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['provider', 'status']);
            $table->index(['company_id', 'created_at']);
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
