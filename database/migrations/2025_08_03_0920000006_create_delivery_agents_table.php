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
            $table->foreignId('user_id')->constrained('users');
            $table->string('da_code')->unique(); // Unique DA identifier
            $table->string('vehicle_number')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('current_location')->nullable(); // GPS coordinates or area
            $table->integer('total_deliveries')->default(0);
            $table->integer('successful_deliveries')->default(0);
            $table->decimal('rating', 3, 2)->default(0.00); // Average rating
            $table->decimal('total_earnings', 15, 2)->default(0.00);
            $table->json('working_hours')->nullable(); // Store working schedule
            $table->json('service_areas')->nullable(); // Areas they can deliver to
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'current_location']);
            $table->index('da_code');
            $table->index('rating');
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