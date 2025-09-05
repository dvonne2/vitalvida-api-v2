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
        Schema::create('salary_deductions', function (Blueprint $table) {
            $table->id();
            
            // Deduction details
            $table->unsignedBigInteger('user_id'); // User whose salary will be deducted
            $table->unsignedBigInteger('violation_id'); // Violation that triggered deduction
            $table->decimal('amount', 15, 2); // Amount to deduct
            $table->text('reason'); // Reason for deduction
            $table->enum('status', [
                'pending',
                'processed',
                'cancelled',
                'failed'
            ])->default('pending');
            
            // Processing details
            $table->date('deduction_date'); // When deduction should be processed
            $table->timestamp('processed_at')->nullable(); // When deduction was processed
            $table->unsignedBigInteger('processed_by')->nullable(); // Who processed the deduction
            $table->text('notes')->nullable(); // Additional notes
            
            // Metadata
            $table->json('metadata')->nullable(); // Additional deduction data
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'deduction_date']);
            $table->index('user_id');
            $table->index('violation_id');
            $table->index('deduction_date');
            $table->index('processed_by');
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('violation_id')->references('id')->on('threshold_violations')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_deductions');
    }
}; 