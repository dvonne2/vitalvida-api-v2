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
        Schema::create('profit_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('payment_reference');
            $table->decimal('amount_received', 15, 2);
            $table->enum('allocated_to', ['marketing', 'opex', 'inventory', 'profit', 'bonus', 'tax']);
            $table->decimal('amount_allocated', 15, 2);
            $table->unsignedBigInteger('bank_account_id');
            $table->enum('allocation_status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('allocated_at')->nullable();
            $table->timestamps();
            
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts');
            $table->index(['payment_reference', 'allocation_status']);
            $table->index(['allocated_to', 'allocation_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profit_allocations');
    }
};
