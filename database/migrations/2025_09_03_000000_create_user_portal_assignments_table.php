<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_portal_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('portal'); // e.g., analytics, auditor, financial-controller
            $table->boolean('can_view')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'portal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_portal_assignments');
    }
};
