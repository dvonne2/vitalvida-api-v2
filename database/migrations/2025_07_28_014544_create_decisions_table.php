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
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->date('decision_date');
            $table->string('decision_title');
            $table->text('context');
            $table->text('outcome');
            $table->text('lesson_learned');
            $table->integer('impact_score')->default(5); // 1-10 scale
            $table->string('department');
            $table->string('decision_maker');
            $table->enum('category', ['strategic', 'operational', 'tactical'])->default('operational');
            $table->json('tags')->nullable(); // For categorization
            $table->timestamps();
            
            $table->index(['decision_date', 'department']);
            $table->index('impact_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decisions');
    }
};
