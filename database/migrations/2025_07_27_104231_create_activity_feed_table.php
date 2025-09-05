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
        Schema::create('activity_feed', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // pickup, delivery, mismatch, call
            $table->text('message');
            $table->string('da_id', 50)->nullable();
            $table->string('order_id', 50)->nullable();
            $table->string('consignment_id', 20)->nullable();
            $table->string('location', 100)->nullable();
            $table->string('status', 50)->nullable(); // info, delivered, flagged
            $table->json('activity_data')->nullable(); // Additional data
            $table->timestamps();
            
            $table->index(['type', 'created_at']);
            $table->index('da_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_feed');
    }
};
