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
        Schema::create('analytics_dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('dashboard_name');
            $table->string('dashboard_type'); // e.g., 'executive', 'financial', 'operational', 'compliance'
            $table->text('description')->nullable();
            $table->json('dashboard_config'); // Widgets, layout, refresh intervals
            $table->json('access_roles'); // Roles that can access this dashboard
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('created_by');
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['dashboard_type', 'is_active']);
            $table->index('is_default');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_dashboards');
    }
}; 