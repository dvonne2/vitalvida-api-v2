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
        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_name');
            $table->string('report_type'); // e.g., 'executive_dashboard', 'financial_report', 'operational_analytics'
            $table->string('report_category'); // e.g., 'daily', 'weekly', 'monthly', 'quarterly', 'custom'
            $table->json('report_config'); // Report parameters, filters, date ranges
            $table->json('report_data'); // Actual report data
            $table->string('status'); // e.g., 'generating', 'completed', 'failed', 'scheduled'
            $table->unsignedBigInteger('template_id')->nullable(); // Reference to report template
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('generated_by')->nullable(); // User who generated the report
            $table->json('recipients')->nullable(); // Email recipients for scheduled reports
            $table->string('file_path')->nullable(); // Path to exported file (PDF, Excel, etc.)
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['report_type', 'status']);
            $table->index(['report_category', 'generated_at']);
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('template_id');
            
            // Foreign key constraint
            $table->foreign('template_id')->references('id')->on('report_templates')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_reports');
    }
}; 