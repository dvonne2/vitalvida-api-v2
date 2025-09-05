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
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('verification_required')->default(false)->after('requires_prepayment');
            $table->boolean('can_auto_progress')->default(true)->after('verification_required');
            $table->timestamp('verified_at')->nullable()->after('assigned_at');
            $table->json('ai_restrictions')->nullable()->after('assignment_reasoning');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'verification_required',
                'can_auto_progress',
                'verified_at',
                'ai_restrictions'
            ]);
        });
    }
};
