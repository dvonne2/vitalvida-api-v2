<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('token'); // FCM or APNS token
            $table->enum('platform', ['android', 'ios'])->default('android');
            $table->json('device_info')->nullable(); // Device details
            $table->boolean('is_active')->default(true);
            $table->timestamp('registered_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
            $table->index(['token', 'is_active']);
            $table->index('registered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
}; 