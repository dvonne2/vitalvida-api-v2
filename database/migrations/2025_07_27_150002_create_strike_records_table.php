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
        Schema::create('strike_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accountant_id')->constrained('accountants')->onDelete('cascade');
            $table->integer('strike_number');
            $table->enum('violation_type', [
                'payment_mismatch', 
                'late_reconciliation', 
                'missing_receipt', 
                'documentation_integrity', 
                'bonus_log_error'
            ]);
            $table->text('violation_description');
            $table->decimal('penalty_amount', 10, 2);
            $table->string('order_id', 50)->nullable();
            $table->json('evidence')->nullable();
            $table->enum('status', ['active', 'resolved', 'disputed'])->default('active');
            $table->date('issued_date');
            $table->date('resolved_date')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('accountants')->onDelete('set null');
            $table->timestamps();
            
            $table->index('accountant_id');
            $table->index('violation_type');
            $table->index('status');
            $table->index('issued_date');
            $table->index('issued_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strike_records');
    }
}; 