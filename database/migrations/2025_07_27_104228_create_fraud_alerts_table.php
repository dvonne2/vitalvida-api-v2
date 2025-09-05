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
        Schema::create('fraud_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_id', 20)->unique(); // VV-2024-001
            $table->string('type', 50); // QUANTITY MISMATCH, DELAYED PICKUP, UNSCANNED WAYBILL
            $table->enum('status', ['active', 'monitoring', 'resolved'])->default('active');
            $table->text('description');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('consignment_id', 20)->nullable();
            $table->string('da_id', 50)->nullable();
            $table->json('escalated_to')->nullable(); // Array of roles
            $table->json('auto_actions')->nullable(); // Array of actions taken
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'severity']);
            $table->index('alert_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraud_alerts');
    }
};
