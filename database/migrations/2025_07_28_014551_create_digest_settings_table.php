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
        Schema::create('digest_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->boolean('enabled')->default(true);
            $table->time('send_time')->default('07:30:00');
            $table->string('email');
            $table->json('template_sections')->nullable(); // Sections to include
            $table->json('custom_filters')->nullable(); // Custom data filters
            $table->string('timezone')->default('Africa/Lagos');
            $table->boolean('include_charts')->default(true);
            $table->boolean('include_attachments')->default(false);
            $table->timestamps();
            
            $table->index(['user_id', 'enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('digest_settings');
    }
};
