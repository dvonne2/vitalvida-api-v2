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
        Schema::create('marketing_intimacy_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->uuid('brand_id');
            $table->decimal('intimacy_score', 5, 2); // relationship strength
            $table->integer('total_interactions')->default(0);
            $table->json('interaction_quality')->nullable(); // quality metrics
            $table->json('preference_data')->nullable(); // learned preferences
            $table->json('emotional_triggers')->nullable(); // what resonates
            $table->date('relationship_started');
            $table->foreignId('company_id')->constrained();
            $table->timestamps();
            
            $table->foreign('brand_id')->references('id')->on('marketing_brands');
            $table->unique(['customer_id', 'brand_id', 'company_id']);
            $table->index(['intimacy_score', 'company_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_intimacy_tracking');
    }
};
