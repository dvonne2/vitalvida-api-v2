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
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('delivery_agent_id');
            $table->string('otp_code', 6);
            $table->enum('otp_type', ['delivery', 'payment', 'pickup'])->default('delivery');
            $table->string('customer_phone', 20);
            $table->enum('status', ['pending', 'verified', 'expired', 'failed'])->default('pending');
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable(); // DA who verified
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->enum('delivery_method', ['sms', 'whatsapp', 'call'])->default('sms');
            $table->boolean('sms_sent')->default(false);
            $table->boolean('whatsapp_sent')->default(false);
            $table->boolean('call_made')->default(false);
            $table->json('delivery_log')->nullable(); // SMS/WhatsApp delivery status
            $table->string('location_lat', 20)->nullable(); // Verification location
            $table->string('location_lng', 20)->nullable();
            $table->string('location_address')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('payment_id');
            $table->index('delivery_agent_id');
            $table->index('otp_code');
            $table->index('customer_phone');
            $table->index('status');
            $table->index('expires_at');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('set null');
            $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};
