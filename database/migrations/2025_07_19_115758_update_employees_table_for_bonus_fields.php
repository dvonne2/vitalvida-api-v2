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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('eligible_for_bonus')->default(true)->after('is_active');
            $table->integer('dependents')->default(0)->after('eligible_for_bonus');
            $table->string('tax_id')->nullable()->after('dependents');
            $table->string('pension_id')->nullable()->after('tax_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['eligible_for_bonus', 'dependents', 'tax_id', 'pension_id']);
        });
    }
};
