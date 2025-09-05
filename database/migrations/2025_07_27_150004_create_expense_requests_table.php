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
        Schema::create('expense_requests', function (Blueprint $table) {
            $table->id();
            $table->string('expense_id', 20)->unique(); // EXP-001, EXP-002, etc.
            $table->foreignId('requested_by')->constrained('accountants')->onDelete('cascade');
            $table->string('department', 100);
            $table->string('expense_type', 100);
            $table->decimal('amount', 15, 2);
            $table->string('vendor_supplier', 255)->nullable();
            $table->string('vendor_phone', 20)->nullable();
            $table->text('description');
            $table->text('business_justification');
            $table->enum('urgency_level', ['normal', 'urgent', 'critical'])->default('normal');
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'escalated'])->default('pending');
            $table->enum('fc_decision', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('gm_decision', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('ceo_decision', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('final_status', ['auto_block', 'escalation', 'manager_review', 'auto_approve'])->default('auto_block');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
            
            $table->index('expense_id');
            $table->index('requested_by');
            $table->index('approval_status');
            $table->index('urgency_level');
            $table->index('submitted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_requests');
    }
}; 