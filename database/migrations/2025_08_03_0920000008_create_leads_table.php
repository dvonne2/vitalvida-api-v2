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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_lead_id')->nullable(); // Link to Zoho CRM
            $table->string('lead_number')->unique();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['new', 'contacted', 'qualified', 'proposal_sent', 'negotiation', 'won', 'lost'])->default('new');
            $table->enum('source', ['website', 'phone_call', 'referral', 'social_media', 'walk_in'])->default('phone_call');
            $table->text('notes')->nullable();
            $table->decimal('potential_value', 15, 2)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users'); // Telesales agent
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->string('whatsapp_otp')->nullable();
            $table->boolean('whatsapp_verified')->default(false);
            $table->timestamp('whatsapp_verified_at')->nullable();
            $table->json('interaction_history')->nullable(); // Store call logs, messages
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'status']);
            $table->index('zoho_lead_id');
            $table->index('customer_phone');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
}; 