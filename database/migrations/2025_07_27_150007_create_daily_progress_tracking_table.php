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
        Schema::create('daily_progress_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accountant_id')->constrained('accountants')->onDelete('cascade');
            $table->date('task_date');
            $table->enum('task_type', [
                'upload_proofs', 
                'process_bonus', 
                'process_payments', 
                'upload_receipt', 
                'escalation_review'
            ]);
            $table->string('task_description', 500);
            $table->decimal('amount', 10, 2)->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('accountant_id');
            $table->index('task_date');
            $table->index('status');
            $table->index('task_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_progress_tracking');
    }
}; 