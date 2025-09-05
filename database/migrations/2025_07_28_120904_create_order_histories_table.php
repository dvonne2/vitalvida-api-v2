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
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action', 100);
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->timestamp('timestamp');
            $table->text('notes')->nullable();
            $table->boolean('auto_action')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['order_id', 'timestamp']);
            $table->index(['staff_id', 'timestamp']);
            $table->index('action');
            $table->index('auto_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_histories');
    }
};
