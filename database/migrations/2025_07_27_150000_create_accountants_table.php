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
        Schema::create('accountants', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 10)->unique(); // ACC001, ACC002, etc.
            $table->string('full_name', 255);
            $table->string('email', 255)->unique();
            $table->string('phone_number', 20)->nullable();
            $table->string('department', 100)->default('Financial Management');
            $table->enum('role', ['accountant', 'financial_controller', 'ceo'])->default('accountant');
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->date('hire_date')->nullable();
            $table->integer('current_strikes')->default(0);
            $table->decimal('total_penalties', 15, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('employee_id');
            $table->index('status');
            $table->index('current_strikes');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accountants');
    }
}; 