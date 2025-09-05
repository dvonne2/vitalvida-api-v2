<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_requests', function (Blueprint $table) {
            $table->id();
            $table->string('service');
            $table->string('method');
            $table->string('path');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('client_type', ['mobile', 'web', 'dashboard', 'unknown'])->default('unknown');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('status', ['success', 'error', 'cache_hit'])->default('success');
            $table->decimal('response_time', 8, 4)->nullable(); // milliseconds
            $table->text('error_message')->nullable();
            $table->string('request_id')->nullable();
            $table->timestamp('timestamp');
            $table->timestamps();

            $table->index(['service', 'status']);
            $table->index(['user_id', 'timestamp']);
            $table->index('timestamp');
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_requests');
    }
}; 