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
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->boolean('is_primary')->default(false); // Primary role for the user
            $table->json('custom_permissions')->nullable(); // Override permissions for this user-role combination
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // Role expiration
            $table->timestamps();
            
            $table->unique(['user_id', 'role_id']);
            $table->index(['user_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
