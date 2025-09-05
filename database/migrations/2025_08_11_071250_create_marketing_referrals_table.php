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
        Schema::create('marketing_referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('brand_id');
            $table->foreignId('referrer_id')->constrained('customers'); // Link to ERP customers
            $table->string('referral_code')->unique();
            $table->decimal('commission_rate', 4, 2);
            $table->integer('total_conversions')->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->enum('status', ['active', 'inactive', 'suspended']);
            $table->json('referral_data')->nullable(); // Store referral campaign data
            $table->foreignId('company_id')->constrained(); // ERP integration
            $table->timestamps();
            
            $table->foreign('brand_id')->references('id')->on('marketing_brands');
            $table->index(['company_id', 'status']);
            $table->index(['referrer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_referrals');
    }
};
