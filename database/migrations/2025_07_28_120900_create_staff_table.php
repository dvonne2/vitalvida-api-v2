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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('staff_type', ['gm', 'telesales_rep', 'delivery_agent', 'coo', 'finance', 'admin']);
            $table->string('state_assigned', 100)->nullable();
            $table->decimal('performance_score', 5, 2)->default(0);
            $table->integer('daily_limit')->default(20);
            $table->enum('status', ['active', 'inactive', 'suspended', 'terminated', 'on_leave'])->default('active');
            $table->date('hire_date')->nullable();
            $table->json('guarantor_info')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(5.00);
            $table->integer('target_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->integer('ghosted_orders')->default(0);
            $table->decimal('total_earnings', 10, 2)->default(0);
            $table->timestamp('last_activity_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'staff_type']);
            $table->index(['state_assigned', 'status']);
            $table->index('performance_score');
            $table->index('last_activity_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
