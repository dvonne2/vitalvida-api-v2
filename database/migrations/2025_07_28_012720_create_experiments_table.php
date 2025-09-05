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
        Schema::create('experiments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description');
            $table->string('type', 50);
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('hypothesis');
            $table->json('success_metrics')->nullable();
            $table->text('control_group')->nullable();
            $table->text('test_group')->nullable();
            $table->integer('sample_size')->nullable();
            $table->decimal('confidence_level', 3, 2)->default(0.95);
            $table->decimal('p_value', 10, 4)->nullable();
            $table->boolean('statistical_significance')->default(false);
            $table->text('results_summary')->nullable();
            $table->text('recommendation')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->decimal('roi', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['status', 'department_id']);
            $table->index(['start_date', 'end_date']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('experiments');
    }
};
