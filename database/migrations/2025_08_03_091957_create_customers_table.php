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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('location');
            $table->integer('total_orders')->default(0);
            $table->integer('abandoned_orders')->default(0);
            $table->integer('completed_orders')->default(0);
            $table->enum('risk_level', ['TRUSTED', 'RISK1', 'RISK2', 'RISK3'])->default('TRUSTED');
            $table->integer('risk_score')->default(0);
            $table->boolean('requires_prepayment')->default(false);
            $table->integer('recovery_orders')->default(0);
            $table->decimal('lifetime_value', 10, 2)->default(0);
            $table->timestamp('last_order_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
