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
        Schema::create('movement_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('movement_type', 50); // warehouse_to_da, da_to_da, da_to_hq
            $table->string('from_location', 100);
            $table->string('to_location', 100);
            $table->string('quantity', 50); // 2-2-2 format
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->string('tracking_number', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['movement_type', 'status']);
            $table->index('tracking_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movement_tracking');
    }
};
