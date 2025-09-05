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
        Schema::create('marketing_customer_presence_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('channel'); // where they actually are
            $table->decimal('engagement_score', 5, 2); // how active they are there
            $table->integer('frequency_hours')->nullable(); // when they're active
            $table->json('behavior_patterns')->nullable(); // what they do there
            $table->decimal('conversion_rate', 5, 4)->default(0); // channel effectiveness
            $table->timestamp('last_active')->nullable();
            $table->foreignId('company_id')->constrained();
            $table->timestamps();
            
            $table->unique(['customer_id', 'channel', 'company_id']);
            $table->index(['engagement_score', 'conversion_rate']);
            $table->index(['company_id', 'last_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketing_customer_presence_map');
    }
};
