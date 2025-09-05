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
            $table->unsignedBigInteger('delivery_agent_id');
            $table->string('otp_code', 6);
            $table->enum('action_type', ['sale', 'stock_deduction', 'transfer', 'adjustment', 'count'])->default('sale');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->timestamp('generated_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->enum('status', ['pending', 'verified', 'failed', 'expired'])->default('pending');
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->timestamps();

            $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents')->onDelete('cascade');

            $table->index(['delivery_agent_id', 'action_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('status');
            $table->index('expires_at');
            $table->index('otp_code');
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