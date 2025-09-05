<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->enum('client_type', ['mobile', 'web', 'dashboard'])->default('mobile');
            $table->string('platform')->nullable(); // android, ios, web
            $table->string('device_id')->nullable();
            $table->string('app_version')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['key', 'is_active']);
            $table->index(['user_id', 'client_type']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
}; 