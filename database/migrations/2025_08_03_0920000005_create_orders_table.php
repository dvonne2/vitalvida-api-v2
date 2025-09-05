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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('zoho_order_id')->nullable(); // Link to Zoho CRM
            $table->foreignId('customer_id')->nullable()->constrained('users'); // If customer is a user
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_email')->nullable();
            $table->text('delivery_address');
            $table->json('items'); // Order items as JSON
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['pending', 'confirmed', 'processing', 'ready_for_delivery', 'assigned', 'in_transit', 'delivered', 'cancelled'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->foreignId('assigned_da_id')->nullable()->constrained('users'); // Delivery Agent
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('delivery_date')->nullable();
            $table->string('delivery_otp')->nullable();
            $table->boolean('otp_verified')->default(false);
            $table->timestamp('otp_verified_at')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['payment_status', 'created_at']);
            $table->index('zoho_order_id');
            $table->index('assigned_da_id');
            $table->index('customer_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
}; 