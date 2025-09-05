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
        Schema::table('orders', function (Blueprint $table) {
            // Add new status values to the status enum
            $table->enum('status', [
                'pending', 
                'pending_payment', 
                'confirmed', 
                'processing', 
                'ready_for_delivery', 
                'assigned', 
                'in_transit', 
                'delivered', 
                'cancelled'
            ])->default('pending')->change();
            
            // Add new status values to the payment_status enum
            $table->enum('payment_status', [
                'pending', 
                'partially_paid', 
                'paid', 
                'failed', 
                'refunded'
            ])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Remove the new status values from the status enum
            $table->enum('status', [
                'pending', 
                'confirmed', 
                'processing', 
                'ready_for_delivery', 
                'assigned', 
                'in_transit', 
                'delivered', 
                'cancelled'
            ])->default('pending')->change();
            
            // Remove the new status values from the payment_status enum
            $table->enum('payment_status', [
                'pending', 
                'paid', 
                'failed', 
                'refunded'
            ])->default('pending')->change();
        });
    }
};
