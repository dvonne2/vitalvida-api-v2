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
        Schema::create('telesales_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone');
            $table->date('employment_start');
            $table->enum('status', ['active', 'inactive', 'terminated'])->default('active');
            $table->decimal('accumulated_bonus', 10, 2)->default(0);
            $table->boolean('bonus_unlocked')->default(false);
            $table->json('weekly_performance')->nullable(); // Store weekly stats
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['status', 'employment_start']);
            $table->index('email');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telesales_agents');
    }
};
