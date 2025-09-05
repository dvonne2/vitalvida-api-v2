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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            
            // Position Information
            $table->string('position_code', 20)->unique(); // POS001, POS002, etc.
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('department_id')->constrained('departments')->onDelete('restrict');
            
            // Salary Range
            $table->decimal('min_salary', 15, 2)->default(0);
            $table->decimal('max_salary', 15, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            
            // Requirements
            $table->text('requirements')->nullable();
            $table->text('responsibilities')->nullable();
            $table->text('qualifications')->nullable();
            $table->text('skills')->nullable();
            
            // Position Details
            $table->enum('level', ['entry', 'junior', 'mid', 'senior', 'lead', 'manager', 'director', 'executive'])->default('entry');
            $table->enum('type', ['full_time', 'part_time', 'contract', 'intern', 'freelance'])->default('full_time');
            $table->string('location', 100)->nullable();
            $table->boolean('remote_allowed')->default(false);
            $table->boolean('hybrid_allowed')->default(false);
            
            // Reporting Structure
            $table->foreignId('reports_to_position_id')->nullable()->constrained('positions')->onDelete('set null');
            $table->integer('direct_reports_count')->default(0);
            
            // Status
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->boolean('is_public')->default(true);
            
            // Metadata
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['position_code', 'status']);
            $table->index(['department_id', 'status']);
            $table->index('level');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
