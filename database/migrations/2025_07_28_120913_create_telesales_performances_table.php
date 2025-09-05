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
        Schema::create('telesales_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->integer('orders_assigned')->default(0);
            $table->integer('orders_attended')->default(0);
            $table->integer('orders_delivered')->default(0);
            $table->integer('orders_ghosted')->default(0);
            $table->decimal('delivery_rate', 5, 2)->default(0);
            $table->boolean('bonus_eligible')->default(false);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->decimal('commission_earned', 10, 2)->default(0);
            $table->decimal('bonus_amount', 10, 2)->default(0);
            $table->decimal('penalties', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['staff_id', 'date']);
            $table->index('date');
            $table->index('delivery_rate');
            $table->index('bonus_eligible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telesales_performances');
    }
};
