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
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('reference_id')->nullable(); // External reference (Moniepoint, etc.)
            $table->foreignId('order_id')->nullable()->constrained('orders');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->enum('payment_method', ['moniepoint', 'cash', 'bank_transfer', 'card'])->default('moniepoint');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded'])->default('pending');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->json('payment_data')->nullable(); // Store payment gateway response
            $table->text('description')->nullable();
            $table->string('zoho_transaction_id')->nullable(); // Link to Zoho Books
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['payment_method', 'created_at']);
            $table->index('reference_id');
            $table->index('zoho_transaction_id');
            $table->index('customer_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
}; 