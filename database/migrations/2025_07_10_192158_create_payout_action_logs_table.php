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
        Schema::create('payout_action_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payout_id');
            $table->string('action');
            $table->unsignedBigInteger('performed_by');
            $table->string('role');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('payout_id');
            $table->foreign('payout_id')->references('id')->on('payouts')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_action_logs');
    }
};
