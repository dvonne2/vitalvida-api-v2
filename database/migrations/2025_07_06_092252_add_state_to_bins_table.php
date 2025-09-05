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
        Schema::table('bins', function (Blueprint $table) {
            $table->string('state')->nullable()->after('location');
            $table->index('state'); // For efficient filtering by state
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->dropIndex(['state']);
            $table->dropColumn('state');
        });
    }
};
