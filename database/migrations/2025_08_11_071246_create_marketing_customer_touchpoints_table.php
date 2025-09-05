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
        Schema::create('marketing_customer_touchpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('customer_id')->constrained(); // Link to ERP customers
            $table->uuid('brand_id');
            $table->string('channel');
            $table->string('touchpoint_type');
            $table->uuid('content_id')->nullable();
            $table->string('interaction_type')->nullable();
            $table->string('whatsapp_provider')->nullable(); // Track which provider was used
            $table->json('metadata')->nullable(); // Store additional touchpoint data
            $table->foreignId('company_id')->constrained(); // ERP integration
            $table->timestamps();
            
            $table->foreign('brand_id')->references('id')->on('marketing_brands');
            $table->foreign('content_id')->references('id')->on('marketing_content_library');
            $table->index(['company_id', 'channel']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['brand_id', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_customer_touchpoints');
    }
};
