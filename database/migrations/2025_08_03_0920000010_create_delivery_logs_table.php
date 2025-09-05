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
        Schema::create('delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('da_id')->constrained('users'); // Delivery Agent
            $table->enum('action', ['assigned', 'picked_up', 'in_transit', 'arrived', 'otp_sent', 'otp_verified', 'delivered', 'failed', 'returned'])->default('assigned');
            $table->string('location')->nullable(); // GPS coordinates or address
            $table->text('notes')->nullable();
            $table->string('otp_code')->nullable();
            $table->boolean('otp_verified')->default(false);
            $table->timestamp('otp_verified_at')->nullable();
            $table->string('customer_signature')->nullable(); // Base64 encoded signature
            $table->json('delivery_photos')->nullable(); // Array of photo URLs
            $table->decimal('delivery_rating', 3, 2)->nullable(); // Customer rating
            $table->text('customer_feedback')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['order_id', 'created_at']);
            $table->index(['da_id', 'action']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_logs');
    }
}; 