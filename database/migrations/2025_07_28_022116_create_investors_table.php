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
        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->enum('role', [
                'master_readiness',
                'tomi_governance', 
                'ron_scale',
                'thiel_strategy',
                'andy_tech',
                'otunba_control',
                'dangote_cost_control',
                'neil_growth'
            ])->default('master_readiness');
            $table->enum('access_level', ['full', 'limited', 'readonly'])->default('limited');
            $table->json('permissions')->nullable(); // Custom permissions for each investor
            $table->json('preferences')->nullable(); // Dashboard preferences, notification settings
            $table->string('company_name')->nullable();
            $table->string('position')->nullable();
            $table->text('bio')->nullable();
            $table->string('profile_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['role', 'is_active']);
            $table->index('access_level');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investors');
    }
};
