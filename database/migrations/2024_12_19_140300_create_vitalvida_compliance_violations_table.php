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
        Schema::create('vitalvida_compliance_violations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('da_id'); // Delivery Agent ID
            $table->string('violation_type'); // photo_compliance, performance, inventory, delivery, behavioral
            $table->text('description');
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->boolean('auto_detected')->default(false);
            $table->string('detection_algorithm')->nullable();
            $table->json('evidence')->nullable(); // Supporting data/evidence
            $table->enum('status', ['pending_action', 'in_progress', 'resolved', 'dismissed']);
            $table->timestamp('detected_at');
            $table->timestamp('action_taken_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable(); // User ID responsible for handling
            $table->text('resolution_notes')->nullable();
            $table->string('enforcement_action')->nullable(); // warning, training, suspension, etc.
            $table->decimal('penalty_amount', 10, 2)->nullable();
            $table->boolean('repeat_violation')->default(false);
            $table->integer('violation_count')->default(1);
            $table->timestamps();

            $table->foreign('da_id')->references('id')->on('vitalvida_delivery_agents')->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['da_id', 'violation_type']);
            $table->index(['severity', 'status']);
            $table->index(['detected_at']);
            $table->index(['auto_detected']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_compliance_violations');
    }
};
