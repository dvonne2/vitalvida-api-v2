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
        Schema::create('money_out_compliance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('delivery_agent_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 8, 2)->default(2500.00); // ₦2,500 standard
            $table->boolean('payment_verified')->default(false);
            $table->boolean('otp_submitted')->default(false);
            $table->boolean('friday_photo_approved')->default(false);
            $table->boolean('three_way_match')->default(false);
            $table->enum('compliance_status', ['ready', 'locked', 'paid'])->default('ready');
            $table->string('proof_of_payment_path')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['compliance_status', 'three_way_match']);
            $table->unique(['order_id']); // One compliance record per order
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('money_out_compliance');
    }
}; 