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
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->string('risk_title');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('probability', ['low', 'medium', 'high', 'confirmed'])->default('medium');
            $table->text('impact_description');
            $table->text('mitigation_plan');
            $table->string('owner');
            $table->enum('status', ['active', 'mitigated', 'escalated', 'resolved', 'planned'])->default('active');
            $table->date('identified_date');
            $table->date('target_resolution_date')->nullable();
            $table->date('resolved_date')->nullable();
            $table->decimal('financial_impact', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['severity', 'probability']);
            $table->index('status');
            $table->index('owner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risks');
    }
};
