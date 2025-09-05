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
        Schema::create('vitalvida_smart_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('recommendation_type'); // allocation, restocking, compliance, performance
            $table->unsignedBigInteger('target_id'); // Agent ID, Product ID, etc.
            $table->string('target_type'); // agent, product, supplier, zone
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->text('recommendation_title');
            $table->text('recommendation_description');
            $table->json('recommendation_data'); // Specific recommendation details
            $table->decimal('confidence_score', 5, 2);
            $table->decimal('impact_score', 5, 2)->nullable(); // Expected impact/benefit
            $table->json('supporting_metrics')->nullable(); // Data supporting the recommendation
            $table->enum('status', ['pending', 'accepted', 'rejected', 'implemented', 'expired']);
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('implemented_at')->nullable();
            $table->unsignedBigInteger('implemented_by')->nullable();
            $table->text('implementation_notes')->nullable();
            $table->json('outcome_metrics')->nullable(); // Results after implementation
            $table->string('algorithm_version')->default('v1.0');
            $table->timestamps();

            $table->foreign('implemented_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['recommendation_type', 'target_id', 'target_type']);
            $table->index(['priority', 'status']);
            $table->index(['generated_at']);
            $table->index(['confidence_score']);
            $table->index(['expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_smart_recommendations');
    }
};
