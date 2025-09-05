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
        Schema::create('delivery_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rating', 2, 1)->default(4.0);
            $table->enum('status', ['available', 'on_route', 'loading', 'offline'])->default('available');
            $table->string('zone');
            $table->enum('vehicle_type', ['motorcycle', 'van', 'truck'])->default('motorcycle');
            $table->integer('current_deliveries')->default(0);
            $table->decimal('success_rate', 5, 2)->default(90);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_agents');
    }
};
