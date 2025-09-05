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
            $table->uuid('brand_id');
            $table->string('name');
            $table->enum('status', ['draft', 'active', 'paused', 'completed']);
            $table->json('channels')->nullable();
            $table->json('whatsapp_providers')->nullable(); // Which providers to use for this campaign
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('budget_total', 10, 2)->nullable();
            $table->decimal('actual_spend', 10, 2)->default(0);
            $table->decimal('actual_revenue', 10, 2)->default(0);
            $table->foreignId('company_id')->constrained(); // ERP integration
            $table->foreignId('created_by')->constrained('users'); // ERP user relation
            $table->timestamps();
            
            $table->foreign('brand_id')->references('id')->on('marketing_brands');
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
