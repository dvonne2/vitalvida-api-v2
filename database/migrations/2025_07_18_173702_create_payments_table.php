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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id', 50)->unique(); // VV-PAY-001
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('customer_id');
            $table->decimal('amount', 10, 2);
            $table->enum('payment_method', ['pos', 'transfer', 'cash', 'card', 'ussd'])->default('pos');
            $table->string('transaction_reference')->nullable(); // Moniepoint reference
            $table->string('moniepoint_reference')->nullable(); // Moniepoint internal reference
            $table->enum('status', ['pending', 'confirmed', 'failed', 'disputed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('pos_terminal_id')->nullable();
            $table->string('merchant_id')->nullable();
            $table->decimal('pos_charges', 8, 2)->default(0.00);
            $table->decimal('net_amount', 10, 2)->nullable(); // Amount after charges
            $table->json('moniepoint_response')->nullable(); // Full webhook response
            $table->string('verification_code', 6)->nullable(); // OTP code
            $table->timestamp('verification_expires_at')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('payment_id');
            $table->index('order_id');
            $table->index('customer_id');
            $table->index('transaction_reference');
            $table->index('status');
            $table->index('paid_at');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
