<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('interaction_type');
            $table->enum('platform', ['meta', 'tiktok', 'google', 'youtube', 'whatsapp', 'sms', 'email'])->nullable();
            $table->json('content_generated')->nullable();
            $table->string('ai_model_used');
            $table->decimal('confidence_score', 3, 2)->default(0);
            $table->boolean('response_received')->default(false);
            $table->boolean('conversion_achieved')->default(false);
            $table->json('performance_metrics')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('revenue_generated', 10, 2)->default(0);
            $table->json('ai_decision_reasoning')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['customer_id', 'interaction_type']);
            $table->index(['platform', 'conversion_achieved']);
            $table->index(['confidence_score', 'created_at']);
            $table->index(['interaction_type', 'created_at']);
            
            // Foreign key
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ai_interactions');
    }
}; 