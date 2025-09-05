<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_auth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('device_id');
            $table->enum('biometric_type', ['fingerprint', 'face_id', 'voice'])->default('fingerprint');
            $table->text('public_key'); // Encrypted public key
            $table->boolean('is_active')->default(true);
            $table->timestamp('registered_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_id']);
            $table->index(['device_id', 'is_active']);
            $table->index('registered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_auth');
    }
}; 