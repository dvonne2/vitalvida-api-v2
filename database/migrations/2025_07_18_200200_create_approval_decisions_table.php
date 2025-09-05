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
        Schema::create('approval_decisions', function (Blueprint $table) {
            $table->id();
            
            // Decision details
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('approver_id'); // User who made the decision
            $table->enum('decision', ['approve', 'reject']);
            $table->text('comments')->nullable(); // Comments from approver
            $table->timestamp('decision_at');
            
            // Metadata
            $table->json('metadata')->nullable(); // Additional decision data
            
            $table->timestamps();
            
            // Indexes
            $table->index(['workflow_id', 'approver_id']);
            $table->index('decision');
            $table->index('decision_at');
            
            // Foreign keys
            $table->foreign('workflow_id')->references('id')->on('approval_workflows')->onDelete('cascade');
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_decisions');
    }
}; 