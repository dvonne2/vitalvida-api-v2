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
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('brand_id')->constrained('marketing_brands')->onDelete('cascade');
            $table->string('name');
            $table->enum('status', ['draft', 'active', 'paused', 'completed', 'cancelled'])->default('draft');
            $table->json('channels'); // facebook, instagram, whatsapp, email, etc.
            $table->json('target_audience')->nullable(); // audience segments
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget_total', 15, 2)->default(0);
            $table->decimal('budget_spent', 15, 2)->default(0);
            $table->decimal('actual_revenue', 15, 2)->default(0);
            $table->boolean('ucx_enabled')->default(true); // Use UCX principles
            $table->json('emotional_objectives')->nullable(); // What emotions to evoke
            $table->json('contextual_triggers')->nullable(); // When to activate campaign
            $table->json('real_time_optimization_rules')->nullable(); // Dynamic optimization rules
            $table->decimal('relevancy_threshold', 3, 1)->default(7.5); // Minimum relevancy score
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['company_id', 'status']);
            $table->index(['brand_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_campaigns');
    }
};
