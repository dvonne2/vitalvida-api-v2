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
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type'); // Polymorphic relationship type
            $table->unsignedBigInteger('approvable_id'); // Polymorphic relationship ID
            $table->unsignedBigInteger('user_id'); // User who performed the action
            $table->enum('action', ['submitted', 'approved', 'rejected', 'cancelled', 'requested_changes']);
            $table->text('comments')->nullable(); // Optional comments
            $table->json('metadata')->nullable(); // Additional data (previous values, etc.)
            $table->timestamp('performed_at'); // When the action was performed
            $table->timestamps();

            // Indexes
            $table->index(['approvable_type', 'approvable_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('performed_at');

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
    }
};
