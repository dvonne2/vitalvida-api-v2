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
        Schema::create('bonus_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->json('bonus_ids');
            $table->decimal('total_amount', 12, 2);
            $table->enum('approval_tier', ['fc', 'gm', 'ceo'])->default('fc');
            $table->json('required_approvers');
            $table->text('justification');
            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'expired'])->default('pending_approval');
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('approval_comments')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('adjusted_amount', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index('approval_tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_approval_requests');
    }
};
