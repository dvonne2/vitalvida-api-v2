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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_id');
            $table->string('receipt_number', 50)->unique();
            $table->timestamp('generated_at');
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->string('email_address')->nullable();
            $table->text('content');
            $table->enum('format', ['text', 'html', 'pdf'])->default('text');
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');

            $table->index('receipt_number');
            $table->index('generated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
}; 