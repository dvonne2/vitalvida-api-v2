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
        Schema::create('bonuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
            $table->enum('bonus_type', ['performance', 'logistics', 'special', 'retention', 'project']);
            $table->text('description');
            $table->decimal('amount', 12, 2);
            $table->date('earned_month');
            $table->json('calculation_basis')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->enum('status', ['calculated', 'pending_approval', 'approved', 'rejected', 'paid'])->default('calculated');
            $table->foreignId('calculated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('approval_comments')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'earned_month']);
            $table->index(['status', 'requires_approval']);
            $table->index('bonus_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonuses');
    }
};
