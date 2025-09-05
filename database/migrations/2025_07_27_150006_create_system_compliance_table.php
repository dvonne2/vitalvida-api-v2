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
        Schema::create('system_compliance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accountant_id')->constrained('accountants')->onDelete('cascade');
            $table->date('compliance_date');
            $table->decimal('payment_matching_rate', 5, 2)->default(0);
            $table->decimal('escalation_discipline_rate', 5, 2)->default(0);
            $table->decimal('documentation_integrity_rate', 5, 2)->default(0);
            $table->decimal('bonus_log_accuracy_rate', 5, 2)->default(0);
            $table->decimal('overall_compliance_score', 5, 2)->default(0);
            $table->decimal('system_health_score', 5, 2)->default(100);
            $table->decimal('cache_hit_rate', 5, 2)->default(0);
            $table->integer('strikes_count')->default(0);
            $table->decimal('penalties_total', 15, 2)->default(0);
            $table->timestamps();
            
            $table->index('accountant_id');
            $table->index('compliance_date');
            $table->index('overall_compliance_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_compliance');
    }
}; 