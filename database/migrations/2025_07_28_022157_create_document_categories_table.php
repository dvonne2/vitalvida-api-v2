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
        Schema::create('document_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Financials, Operations, Governance, Vision & Strategy
            $table->text('description')->nullable();
            $table->json('required_for_investor_type')->nullable(); // Which investor roles need this category
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('icon')->nullable(); // FontAwesome icon class
            $table->string('color')->nullable(); // Hex color for UI
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('display_order');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_categories');
    }
};
