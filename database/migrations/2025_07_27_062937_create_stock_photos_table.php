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
        Schema::create('stock_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained('delivery_agents')->onDelete('cascade');
            $table->text('photo_data'); // Base64 encoded photo
            $table->json('stock_levels')->nullable(); // Current stock levels
            $table->enum('photo_quality', ['clear', 'unclear'])->default('clear');
            $table->timestamp('uploaded_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_photos');
    }
};
