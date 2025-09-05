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
        Schema::create('campaign_performance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->onDelete('cascade');
            $table->foreignId('creative_asset_id')->nullable()->constrained('creative_assets')->onDelete('cascade');
            $table->enum('platform', [
                'facebook', 
                'instagram', 
                'tiktok', 
                'google', 
                'email', 
                'sms'
            ]);
            $table->date('date');
            
            // Basic metrics
            $table->bigInteger('impressions')->default(0);
            $table->bigInteger('reach')->default(0);
            $table->bigInteger('clicks')->default(0);
            $table->bigInteger('conversions')->default(0);
            $table->decimal('spend', 15, 2)->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            
            // Calculated rates
            $table->decimal('engagement_rate', 8, 4)->nullable();
            $table->decimal('click_through_rate', 8, 4)->nullable();
            $table->decimal('conversion_rate', 8, 4)->nullable();
            $table->decimal('cost_per_click', 10, 2)->nullable();
            $table->decimal('cost_per_conversion', 10, 2)->nullable();
            $table->decimal('return_on_ad_spend', 8, 2)->nullable();
            $table->decimal('quality_score', 5, 2)->nullable();
            
            // Video metrics
            $table->decimal('audience_retention', 8, 4)->nullable();
            $table->bigInteger('video_views')->default(0);
            $table->decimal('video_completion_rate', 8, 4)->nullable();
            
            // Social engagement
            $table->bigInteger('shares')->default(0);
            $table->bigInteger('comments')->default(0);
            $table->bigInteger('likes')->default(0);
            $table->bigInteger('saves')->default(0);
            
            // Action metrics
            $table->bigInteger('link_clicks')->default(0);
            $table->bigInteger('profile_visits')->default(0);
            $table->bigInteger('follows')->default(0);
            $table->bigInteger('messages')->default(0);
            $table->bigInteger('phone_calls')->default(0);
            $table->bigInteger('direction_requests')->default(0);
            $table->bigInteger('website_visits')->default(0);
            $table->bigInteger('app_installs')->default(0);
            
            // E-commerce metrics
            $table->bigInteger('purchases')->default(0);
            $table->bigInteger('add_to_cart')->default(0);
            $table->bigInteger('initiate_checkout')->default(0);
            $table->bigInteger('complete_registration')->default(0);
            
            // Additional data
            $table->json('custom_events')->nullable();
            $table->json('demographics')->nullable();
            $table->json('placement_performance')->nullable();
            $table->json('device_performance')->nullable();
            $table->json('time_performance')->nullable();
            $table->json('audience_insights')->nullable();
            $table->json('competitor_benchmarks')->nullable();
            $table->json('trend_analysis')->nullable();
            $table->json('optimization_suggestions')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('tracked_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['campaign_id', 'date']);
            $table->index(['creative_asset_id', 'date']);
            $table->index(['platform', 'date']);
            $table->index(['date', 'platform']);
            $table->unique(['campaign_id', 'creative_asset_id', 'platform', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_performance');
    }
};
