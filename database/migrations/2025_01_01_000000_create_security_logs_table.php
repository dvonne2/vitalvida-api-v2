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
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('event_type'); // login, logout, failed_login, password_change, etc.
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('request_method', 10);
            $table->string('request_path');
            $table->integer('status_code');
            $table->json('request_data')->nullable(); // Sanitized request data
            $table->json('response_data')->nullable(); // Sanitized response data
            $table->string('session_id')->nullable();
            $table->string('request_id')->unique(); // For tracking requests
            $table->integer('duration_ms')->nullable(); // Request duration
            $table->text('error_message')->nullable(); // For failed requests
            $table->string('risk_level')->default('low'); // low, medium, high, critical
            $table->boolean('is_suspicious')->default(false);
            $table->timestamps();
            
            // Indexes for performance and security analysis
            $table->index(['user_id', 'created_at']);
            $table->index(['event_type', 'created_at']);
            $table->index(['ip_address', 'created_at']);
            $table->index(['risk_level', 'created_at']);
            $table->index(['is_suspicious', 'created_at']);
            $table->index('request_id');
        });

        Schema::create('failed_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->timestamp('attempted_at');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_until')->nullable();
            
            // Indexes for rate limiting
            $table->index(['email', 'ip_address', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
            $table->index(['is_locked', 'locked_until']);
        });

        Schema::create('api_rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('key'); // Rate limit key (email|ip, ip, etc.)
            $table->string('type'); // auth, api, etc.
            $table->integer('attempts')->default(0);
            $table->timestamp('reset_at');
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('blocked_until')->nullable();
            
            // Indexes for rate limiting
            $table->index(['key', 'type']);
            $table->index(['is_blocked', 'blocked_until']);
            $table->index('reset_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_rate_limits');
        Schema::dropIfExists('failed_login_attempts');
        Schema::dropIfExists('security_logs');
    }
}; 