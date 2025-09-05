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
        Schema::create('vitalvida_delivery_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('email')->nullable();
            $table->string('location');
            $table->string('zone')->default('Lagos');
            $table->decimal('rating', 3, 2)->default(0);
            $table->enum('status', ['Active', 'Inactive', 'On Delivery', 'Break'])->default('Active');
            $table->integer('compliance_score')->default(100);
            $table->integer('violation_count')->default(0);
            $table->string('last_compliance_action')->nullable();
            $table->timestamp('compliance_updated_at')->nullable();
            $table->timestamp('last_compliance_check')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_delivery_agents');
    }
};
