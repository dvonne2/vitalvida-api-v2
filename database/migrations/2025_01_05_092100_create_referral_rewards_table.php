<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id');
            $table->unsignedBigInteger('referee_order_id');
            $table->integer('cash_naira')->default(1500);
            $table->boolean('free_delivery')->default(true);
            $table->enum('status', ['pending', 'credited', 'redeemed'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('referrer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('referee_order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index(['referrer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_rewards');
    }
};
