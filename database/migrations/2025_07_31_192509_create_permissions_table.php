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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // dashboard.view, users.create, inventory.manage, etc.
            $table->string('display_name'); // View Dashboard, Create Users, Manage Inventory
            $table->string('module'); // dashboard, users, inventory, finance, hr, marketing, logistics
            $table->string('action'); // view, create, update, delete, manage, approve
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['module', 'action']);
            $table->index(['name', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
