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
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->string('production_number', 50)->unique();
            $table->date('date');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'approved'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->decimal('total_cost', 15, 2)->default(0.00);
            $table->integer('total_produced')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['status', 'date']);
            $table->index('production_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
}; 