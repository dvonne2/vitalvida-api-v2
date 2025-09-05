<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_limit_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('service');
            $table->enum('client_type', ['mobile', 'web', 'dashboard'])->default('mobile');
            $table->integer('max_requests');
            $table->integer('window_seconds');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'service', 'client_type']);
            $table->index(['service', 'client_type']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_limit_rules');
    }
}; 