<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('deliveries')) {
            Schema::create('deliveries', function (Blueprint $table) {
                $table->id();
                $table->string('delivery_code')->unique();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('delivery_agent_id');
                $table->unsignedBigInteger('assigned_by')->nullable();
                
                $table->enum('status', [
                    'assigned', 'picked_up', 'in_transit', 'delivered', 
                    'failed', 'returned', 'cancelled'
                ])->default('assigned');
                
                $table->string('pickup_location')->nullable();
                $table->string('delivery_location');
                $table->string('recipient_name');
                $table->string('recipient_phone');
                $table->text('delivery_notes')->nullable();
                
                $table->timestamp('assigned_at');
                $table->timestamp('picked_up_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                
                $table->string('delivery_otp', 6)->nullable();
                $table->boolean('otp_verified')->default(false);
                $table->integer('delivery_attempts')->default(1);
                $table->text('failure_reason')->nullable();
                $table->decimal('distance_km', 8, 2)->nullable();
                $table->integer('delivery_time_minutes')->nullable();
                $table->integer('customer_rating')->nullable();
                $table->text('customer_feedback')->nullable();
                
                $table->timestamps();
                $table->softDeletes();
                
                $table->index(['delivery_agent_id', 'status']);
                $table->index('delivery_code');
                $table->index('assigned_at');
                
                $table->foreign('delivery_agent_id')->references('id')->on('delivery_agents');
                $table->foreign('assigned_by')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
