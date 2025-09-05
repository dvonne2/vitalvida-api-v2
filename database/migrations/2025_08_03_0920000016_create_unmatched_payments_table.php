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
        Schema::create('unmatched_payments', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->string('reference_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->enum('payment_method', ['moniepoint', 'cash', 'bank_transfer', 'card'])->default('moniepoint');
            $table->enum('status', ['unmatched', 'matched', 'refunded', 'expired'])->default('unmatched');
            $table->json('payment_data')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users');
            $table->foreignId('matched_to_order_id')->nullable()->constrained('orders');
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'created_at']);
            $table->index('customer_phone');
            $table->index('reference_id');
            $table->index('matched_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unmatched_payments');
    }
}; 