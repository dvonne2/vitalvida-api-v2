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
        Schema::create('alert_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['fraud_alert', 'stock_emergency', 'da_performance', 'payment_mismatch']);
            $table->text('sms_template');
            $table->text('whatsapp_template');
            $table->json('recipients'); // GM_PRIMARY, COO_BACKUP, etc.
            $table->enum('priority', ['low', 'medium', 'high', 'critical']);
            $table->json('auto_escalation_rules')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alert_templates');
    }
};
