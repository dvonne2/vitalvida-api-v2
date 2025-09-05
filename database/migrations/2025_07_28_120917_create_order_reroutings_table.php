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
        Schema::create('order_reroutings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('from_staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('to_staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('reason', 100);
            $table->timestamp('timestamp');
            $table->enum('success_status', ['pending', 'success', 'failed'])->default('pending');
            $table->text('notes')->nullable();
            $table->boolean('auto_rerouted')->default(false);
            $table->foreignId('rerouted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['order_id', 'timestamp']);
            $table->index(['from_staff_id', 'timestamp']);
            $table->index(['to_staff_id', 'timestamp']);
            $table->index('reason');
            $table->index('success_status');
            $table->index('auto_rerouted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_reroutings');
    }
};
