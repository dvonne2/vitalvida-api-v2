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
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->nullable();
            $table->string('action');
            $table->string('item_id')->nullable();
            $table->string('item_name')->nullable();
            $table->string('from_bin')->nullable();
            $table->string('to_bin')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('zoho_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['action', 'created_at']);
            $table->index('user_id');
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
