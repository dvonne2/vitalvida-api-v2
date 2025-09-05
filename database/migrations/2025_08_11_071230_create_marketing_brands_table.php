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
        Schema::create('marketing_brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('logo_url')->nullable();
            $table->json('brand_voice')->nullable();
            $table->json('target_audience')->nullable();
            $table->json('whatsapp_config')->nullable(); // Provider preferences per brand
            $table->foreignId('company_id')->constrained(); // ERP company relation
            $table->foreignId('created_by')->constrained('users'); // ERP user relation
            $table->timestamps();
            
            $table->index(['company_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_brands');
    }
};
