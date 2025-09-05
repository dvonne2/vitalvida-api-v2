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
        Schema::create('other_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_id')->unique(); // e.g., EXP-001
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->string('department');
            $table->string('expense_type'); // airtime, cleaning, repairs, etc.
            $table->text('description');
            $table->decimal('amount', 10, 2);
            $table->string('vendor_name');
            $table->string('vendor_phone');
            $table->enum('urgency_level', ['normal', 'urgent'])->default('normal');
            $table->text('business_justification');
            $table->string('receipt_path')->nullable();
            $table->enum('approval_required', ['fc', 'gm', 'ceo'])->default('fc');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('approval_reference')->nullable(); // e.g., FC-APP-001
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index(['approval_status', 'approval_required']);
            $table->index(['requested_by', 'expense_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('other_expenses');
    }
}; 