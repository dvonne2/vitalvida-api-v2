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
        Schema::create('transaction_locks', function (Blueprint $table) {
            $table->id();
            $table->enum('module', ['Sales', 'Purchases', 'Banking', 'Payroll', 'Inventory']);
            $table->date('locked_till');
            $table->unsignedBigInteger('locked_by');
            $table->text('lock_reason')->nullable();
            $table->timestamp('locked_at');
            $table->timestamps();
            
            $table->foreign('locked_by')->references('id')->on('users');
            $table->unique(['module', 'locked_till']);
            $table->index(['module', 'locked_till']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_locks');
    }
};
