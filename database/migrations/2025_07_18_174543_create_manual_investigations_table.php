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
        Schema::create('manual_investigations', function (Blueprint $table) {
            $table->id();
            $table->string('investigation_id', 50)->unique(); // VV-INVEST-001
            $table->enum('type', ['payment_verification_failed', 'webhook_processing_failed', 'system_error', 'data_integrity_issue']);
            $table->json('data'); // Relevant data for investigation
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'escalated'])->default('pending');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->index('investigation_id');
            $table->index('type');
            $table->index('status');
            $table->index('priority');
            $table->index('assigned_to');
            
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_investigations');
    }
};
