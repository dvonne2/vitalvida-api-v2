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
        Schema::create('account_managers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rating', 2, 1)->default(4.0);
            $table->enum('status', ['active', 'inactive', 'busy'])->default('active');
            $table->json('specialties')->nullable();
            $table->decimal('conversion_rate', 5, 2)->default(0);
            $table->integer('avg_assignment_time')->default(30);
            $table->integer('current_load')->default(0);
            $table->string('region')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_managers');
    }
};
