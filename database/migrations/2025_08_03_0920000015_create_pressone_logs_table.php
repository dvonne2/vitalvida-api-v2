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
        Schema::create('pressone_logs', function (Blueprint $table) {
            $table->id();
            $table->string('api_endpoint');
            $table->enum('method', ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])->default('GET');
            $table->enum('status', ['pending', 'success', 'failed', 'timeout'])->default('pending');
            $table->integer('response_code')->nullable();
            $table->json('request_data')->nullable();
            $table->json('response_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('response_time_ms')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('ip_address')->nullable();
            $table->json('headers')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['api_endpoint', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('user_id');
            $table->index('response_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pressone_logs');
    }
}; 