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
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->string('escalation_id', 50)->unique(); // VV-ESC-001
            $table->enum('escalation_type', ['logistics_cost', 'other_expense', 'compliance_issue', 'performance_issue']);
            $table->unsignedBigInteger('escalatable_id'); // ID of the escalated item
            $table->string('escalatable_type'); // Model type (LogisticsCost, OtherExpense, etc.)
            $table->unsignedBigInteger('created_by'); // User who created escalation
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_review', 'approved', 'rejected', 'resolved'])->default('pending');
            $table->string('title');
            $table->text('description');
            $table->text('business_justification')->nullable();
            $table->decimal('amount_involved', 10, 2)->nullable();
            $table->enum('required_approval', ['fc', 'gm', 'ceo']);
            $table->unsignedBigInteger('assigned_to')->nullable(); // FC/GM/CEO user ID
            $table->timestamp('assigned_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->json('attachments')->nullable(); // File paths
            $table->timestamps();

            $table->index('escalation_id');
            $table->index(['escalatable_type', 'escalatable_id']);
            $table->index('created_by');
            $table->index('status');
            $table->index('priority');
            $table->index('assigned_to');
            $table->index('due_date');
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
