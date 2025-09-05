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
        Schema::create('kyc_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('document_type', ['national_id', 'passport', 'drivers_license', 'utility_bill', 'bank_statement', 'selfie'])->default('national_id');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->string('document_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->json('document_data')->nullable(); // Store document details
            $table->json('verification_data')->nullable(); // Store verification results
            $table->text('rejection_reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'document_type', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('verified_by');
            $table->index('document_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc_logs');
    }
}; 