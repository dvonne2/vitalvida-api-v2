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
            $table->string('order_id')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->string('product_name');
            $table->integer('quantity');
            $table->decimal('amount', 10, 2);
            $table->enum('source', ['facebook_ads', 'instagram', 'whatsapp', 'referral']);
            $table->enum('status', ['received', 'assigned_to_am', 'assigned_to_da', 'payment_received', 'completed', 'abandoned'])->default('received');
            $table->enum('risk_level', ['TRUSTED', 'RISK1', 'RISK2', 'RISK3'])->default('TRUSTED');
            $table->boolean('is_prepaid')->default(false);
            $table->boolean('requires_prepayment')->default(false);
            $table->foreignId('account_manager_id')->nullable()->constrained();
            $table->foreignId('delivery_agent_id')->nullable()->constrained();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('payment_received_at')->nullable();
            $table->json('assignment_reasoning')->nullable();
            $table->timestamps();
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
