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
        Schema::create('agent_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('delivery_agents')->onDelete('cascade');
            
            // Essential Requirements
            $table->boolean('has_smartphone')->default(false);
            $table->boolean('has_transportation')->default(false);
            $table->string('transportation_type', 100)->nullable();
            $table->boolean('has_drivers_license')->default(false);
            $table->boolean('can_store_products')->default(false);
            $table->boolean('comfortable_with_portal')->default(false);
            
            // Delivery Areas
            $table->json('delivery_areas')->nullable(); // Array of cities
            
            // Additional Requirements
            $table->boolean('has_bank_account')->default(false);
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('account_name', 255)->nullable();
            
            // Communication Preferences
            $table->enum('preferred_communication', ['whatsapp', 'phone', 'email'])->default('whatsapp');
            $table->boolean('can_receive_notifications')->default(true);
            
            // Availability
            $table->json('availability_hours')->nullable(); // Working hours
            $table->boolean('available_weekends')->default(false);
            $table->boolean('available_holidays')->default(false);
            
            // Experience
            $table->enum('delivery_experience', ['none', 'less_than_1_year', '1_3_years', '3_5_years', '5_plus_years'])->default('none');
            $table->text('previous_experience')->nullable();
            
            // Requirements Score
            $table->integer('requirements_score')->default(0);
            $table->boolean('meets_minimum_requirements')->default(false);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['agent_id', 'meets_minimum_requirements']);
            $table->index('requirements_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_requirements');
    }
};
