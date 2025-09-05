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
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            
            // Workflow details
            $table->unsignedBigInteger('violation_id');
            $table->string('workflow_type'); // fc_gm_dual, gm_ceo_dual, fc_only, gm_only
            $table->json('required_approvers'); // Array of required approver roles
            $table->integer('timeout_hours'); // Hours until auto-reject
            $table->boolean('auto_reject_on_timeout')->default(true);
            
            // Status tracking
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'timeout_rejected'
            ])->default('pending');
            
            // Approval tracking
            $table->json('approvals_received')->nullable(); // Array of approver IDs who approved
            $table->json('rejections_received')->nullable(); // Array of approver IDs who rejected
            $table->timestamp('expires_at'); // When workflow expires
            $table->timestamp('completed_at')->nullable(); // When workflow was completed
            
            // Metadata
            $table->json('metadata')->nullable(); // Additional workflow data
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'expires_at']);
            $table->index('workflow_type');
            $table->index('violation_id');
            $table->index('expires_at');
            
            // Foreign keys
            $table->foreign('violation_id')->references('id')->on('threshold_violations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_workflows');
    }
}; 