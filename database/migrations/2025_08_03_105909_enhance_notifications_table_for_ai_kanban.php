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
        Schema::table('notifications', function (Blueprint $table) {
            // Update the type enum to include new notification types
            $table->enum('type', [
                'assignment_timeout', 
                'payment_received', 
                'performance_alert', 
                'system_update',
                'manual_review_required',
                'kanban_blocked'
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [
                'assignment_timeout', 
                'payment_received', 
                'performance_alert', 
                'system_update'
            ])->change();
        });
    }
};
