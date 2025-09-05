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
        Schema::create('fraud_patterns', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['PAYMENT_FRAUD', 'DELIVERY_FRAUD', 'GHOST_ORDER_PATTERN', 'INVENTORY_FRAUD', 'STAFF_COLLUSION']);
            $table->foreignId('staff_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->integer('confidence_score'); // 1-100
            $table->decimal('risk_amount', 12, 2)->default(0);
            $table->timestamp('detected_at');
            $table->enum('status', ['AUTO_BLOCKED', 'INVESTIGATING', 'BLOCKED', 'FALSE_ALARM', 'RESOLVED'])->default('INVESTIGATING');
            $table->json('evidence')->nullable();
            $table->text('auto_action_taken')->nullable();
            $table->boolean('gm_notified')->default(false);
            $table->text('investigation_notes')->nullable();
            $table->timestamp('investigation_started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'status']);
            $table->index(['confidence_score', 'detected_at']);
            $table->index('gm_notified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraud_patterns');
    }
};
