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
        Schema::create('roadmaps', function (Blueprint $table) {
            $table->id();
            $table->string('initiative');
            $table->string('owner');
            $table->integer('completion_percentage')->default(0);
            $table->string('quarter'); // Q1, Q2, Q3, Q4
            $table->enum('status', ['good', 'monitor', 'fix', 'completed'])->default('monitor');
            $table->json('milestones')->nullable(); // Store milestone data
            $table->decimal('current_value', 15, 2)->nullable(); // Current progress value
            $table->decimal('target_value', 15, 2)->nullable(); // Target value
            $table->string('value_unit')->nullable(); // e.g., 'DAs', 'revenue', 'percentage'
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['quarter', 'status']);
            $table->index('owner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roadmaps');
    }
};
