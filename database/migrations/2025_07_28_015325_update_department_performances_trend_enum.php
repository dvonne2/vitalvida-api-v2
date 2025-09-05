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
        Schema::table('department_performances', function (Blueprint $table) {
            // Drop the existing enum column
            $table->dropColumn('trend');
        });

        Schema::table('department_performances', function (Blueprint $table) {
            // Recreate with the new enum values
            $table->enum('trend', ['improving', 'stable', 'declining', 'concerning', 'increasing'])->default('stable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('department_performances', function (Blueprint $table) {
            $table->dropColumn('trend');
        });

        Schema::table('department_performances', function (Blueprint $table) {
            $table->enum('trend', ['improving', 'stable', 'declining', 'concerning'])->default('stable');
        });
    }
};
