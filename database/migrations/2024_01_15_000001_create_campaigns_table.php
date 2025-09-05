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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('objective', [
                'awareness', 
                'consideration', 
                'conversions', 
                'app_installs', 
                'video_views', 
                'lead_generation'
            ])->default('awareness');
            $table->enum('status', [
                'draft', 
                'active', 
                'paused', 
                'completed', 
                'archived'
            ])->default('draft');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget', 15, 2)->default(0);
            $table->enum('budget_type', ['daily', 'lifetime'])->default('lifetime');
            $table->json('target_audience')->nullable();
            $table->json('platforms')->nullable();
            $table->string('ad_account_id')->nullable();
            $table->string('campaign_id_external')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->json('tags')->nullable();
            $table->json('performance_goals')->nullable();
            $table->json('creative_brief')->nullable();
            $table->json('brand_guidelines')->nullable();
            $table->json('competitor_analysis')->nullable();
            $table->json('target_metrics')->nullable();
            $table->json('success_criteria')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_automated')->default(false);
            $table->json('automation_rules')->nullable();
            $table->boolean('ai_optimization_enabled')->default(false);
            $table->json('ai_optimization_settings')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'start_date']);
            $table->index(['objective', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['priority', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
