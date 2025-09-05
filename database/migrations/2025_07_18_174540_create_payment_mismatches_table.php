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
        Schema::create('payment_mismatches', function (Blueprint $table) {
            $table->id();
            $table->string('mismatch_id', 50)->unique(); // VV-MISMATCH-001
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('payment_id');
            $table->string('entered_phone', 20); // Phone entered by accountant
            $table->string('entered_order_id', 50); // Order ID entered by accountant
            $table->string('actual_phone', 20); // Actual customer phone
            $table->string('actual_order_id', 50); // Actual order ID
            $table->enum('mismatch_type', ['order_id', 'phone', 'both', 'unknown'])->default('unknown');
            $table->decimal('payment_amount', 10, 2); // Amount involved
            $table->json('webhook_payload'); // Full webhook data
            $table->boolean('investigation_required')->default(true);
            $table->text('investigation_notes')->nullable();
            $table->timestamp('investigated_at')->nullable();
            $table->unsignedBigInteger('investigated_by')->nullable();
            $table->enum('corrective_action', ['reprocess_payment', 'contact_customer', 'manual_override'])->nullable();
            $table->enum('resolution_type', ['corrected', 'customer_error', 'system_error'])->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->decimal('penalty_amount', 8, 2)->default(10000.00); // ₦10,000 penalty
            $table->boolean('penalty_applied')->default(false);
            $table->timestamps();

            $table->index('mismatch_id');
            $table->index('order_id');
            $table->index('payment_id');
            $table->index('mismatch_type');
            $table->index('investigation_required');
            $table->index('resolved_at');
            
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('investigated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_mismatches');
    }
};
