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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // superadmin, ceo, cfo, operations_manager, hr_manager, media_buyer, inventory_manager, telesales_agent
            $table->string('display_name'); // Super Admin, CEO, CFO, Operations Manager, HR Manager, Media Buyer, Inventory Manager, Telesales Agent
            $table->text('description')->nullable();
            $table->json('permissions')->nullable(); // Store permissions as JSON for flexibility
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // For role hierarchy
            $table->timestamps();
            
            $table->index(['name', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
