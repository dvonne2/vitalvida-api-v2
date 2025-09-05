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
        Schema::create('marketing_relevancy_scoring', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->uuid('content_id');
            $table->decimal('relevancy_score', 5, 2); // how relevant to this customer
            $table->json('relevancy_factors')->nullable(); // why it's relevant
            $table->string('customer_stage'); // awareness, consideration, decision
            $table->json('personalization_data')->nullable();
            $table->timestamp('scored_at');
            $table->foreignId('company_id')->constrained();
            $table->timestamps();
            
            $table->foreign('content_id')->references('id')->on('marketing_content_library');
            $table->index(['relevancy_score', 'customer_stage']);
            $table->index(['company_id', 'scored_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_relevancy_scoring');
    }
};
