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
        // Drop the old table if it exists
        Schema::dropIfExists('threshold_violations');
        
        // Create the new table with correct structure
        Schema::create('threshold_violations', function (Blueprint $table) {
            $table->id();
            $table->string('cost_type'); // logistics, expense, bonus
            $table->string('cost_category')->nullable(); // generator_fuel, equipment_repair, etc.
            $table->decimal('amount', 12, 2); // Original amount requested
            $table->decimal('threshold_limit', 12, 2); // Threshold that was exceeded
            $table->decimal('overage_amount', 12, 2); // Amount over the threshold
            $table->json('violation_details')->nullable(); // Detailed violation breakdown
            $table->enum('status', ['blocked', 'approved', 'rejected', 'processed'])->default('blocked');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->string('reference_type')->nullable(); // Polymorphic relation
            $table->unsignedBigInteger('reference_id')->nullable(); // Polymorphic relation
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['cost_type', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threshold_violations');
    }
};
