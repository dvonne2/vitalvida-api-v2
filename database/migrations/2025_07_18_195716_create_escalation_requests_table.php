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
        Schema::create('escalation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('threshold_violation_id')->constrained()->cascadeOnDelete();
            $table->string('escalation_type'); // threshold_violation, high_value_bonus, etc.
            $table->decimal('amount_requested', 12, 2);
            $table->decimal('threshold_limit', 12, 2);
            $table->decimal('overage_amount', 12, 2);
            $table->json('approval_required'); // Array of required approver roles
            $table->text('escalation_reason');
            $table->text('business_justification')->nullable();
            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'expired'])->default('pending_approval');
            $table->enum('priority', ['normal', 'medium', 'high', 'critical'])->default('normal');
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('final_decision_at')->nullable();
            $table->enum('final_outcome', ['approved', 'rejected', 'expired'])->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['priority', 'created_at']);
            $table->index(['escalation_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escalation_requests');
    }
};
