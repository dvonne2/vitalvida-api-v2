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
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            $table->string('consignment_id', 20)->unique(); // VV-2024-001
            $table->string('from_location', 100);
            $table->string('to_location', 100);
            $table->string('quantity', 50); // 2-2-2 format
            $table->string('port', 100)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 20)->nullable();
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'cancelled'])->default('pending');
            $table->timestamp('pickup_time')->nullable();
            $table->timestamp('delivery_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('consignment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consignments');
    }
};
