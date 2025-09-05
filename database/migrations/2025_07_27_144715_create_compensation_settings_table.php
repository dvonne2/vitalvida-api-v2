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
        Schema::create('compensation_settings', function (Blueprint $table) {
            $table->id();
            
            // Basic Compensation
            $table->decimal('pickup_return_amount', 10, 2)->default(1500.00); // ₦1,500
            $table->decimal('maximum_per_delivery', 10, 2)->default(2500.00); // Maximum ₦2,500
            $table->decimal('minimum_per_delivery', 10, 2)->default(500.00); // Minimum ₦500
            
            // Payment Configuration
            $table->enum('payment_frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->enum('payment_method', ['portal', 'bank_transfer', 'mobile_money', 'cash'])->default('portal');
            $table->decimal('payment_threshold', 10, 2)->default(5000.00); // Minimum amount for payment
            
            // Commission Structure
            $table->decimal('base_commission_rate', 5, 2)->default(10.00); // 10%
            $table->decimal('bonus_commission_rate', 5, 2)->default(5.00); // 5% bonus
            $table->integer('bonus_delivery_threshold')->default(50); // Deliveries needed for bonus
            
            // Performance Bonuses
            $table->decimal('on_time_delivery_bonus', 10, 2)->default(200.00);
            $table->decimal('customer_satisfaction_bonus', 10, 2)->default(300.00);
            $table->decimal('referral_bonus', 10, 2)->default(1000.00);
            
            // Deductions
            $table->decimal('late_delivery_penalty', 10, 2)->default(100.00);
            $table->decimal('customer_complaint_penalty', 10, 2)->default(200.00);
            $table->decimal('damage_penalty', 10, 2)->default(500.00);
            
            // System Settings
            $table->boolean('active')->default(true);
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_until')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['active', 'effective_from']);
            $table->index('payment_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compensation_settings');
    }
};
