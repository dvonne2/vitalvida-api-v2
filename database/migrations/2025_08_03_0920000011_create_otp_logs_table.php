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
        Schema::create('otp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('otp_code');
            $table->string('phone_number');
            $table->enum('type', ['delivery', 'whatsapp', 'kyc', 'login', 'password_reset'])->default('delivery');
            $table->enum('status', ['sent', 'verified', 'expired', 'failed'])->default('sent');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('order_id')->nullable()->constrained('orders');
            $table->foreignId('lead_id')->nullable()->constrained('leads');
            $table->json('metadata')->nullable(); // Additional context data
            $table->timestamps();
            
            // Indexes
            $table->index(['phone_number', 'type', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('otp_code');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_logs');
    }
}; 