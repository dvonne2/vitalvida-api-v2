<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('zoho_storage_id')->unique();
            $table->string('zoho_warehouse_id');
            $table->string('assigned_to_da')->nullable();
            $table->string('da_phone')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->enum('type', ['delivery_agent', 'main_storage', 'staging'])->default('delivery_agent');
            $table->decimal('max_capacity', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('assigned_to_da');
            $table->index('status');
            $table->index('type');
            $table->index('zoho_storage_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bins');
    }
};
