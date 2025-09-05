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
        Schema::create('agent_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('delivery_agents')->onDelete('cascade');
            
            // Document Information
            $table->enum('document_type', ['passport_photo', 'government_id', 'utility_bill', 'drivers_license', 'bank_statement'])->notNull();
            $table->string('file_path', 500)->notNull();
            $table->string('file_name', 255)->notNull();
            $table->integer('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('file_hash', 64)->nullable(); // For duplicate detection
            
            // Verification Status
            $table->enum('verification_status', ['pending', 'verified', 'rejected', 'processing'])->default('pending');
            $table->decimal('ai_verification_score', 5, 2)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->json('ai_analysis_result')->nullable(); // Detailed AI analysis
            
            // Verification Tracking
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('verified_by', 100)->nullable(); // AI or admin username
            
            // Document Metadata
            $table->json('document_metadata')->nullable(); // Extracted data from document
            $table->boolean('is_duplicate')->default(false);
            $table->string('duplicate_of_document_id')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['agent_id', 'document_type']);
            $table->index(['verification_status', 'ai_verification_score']);
            $table->index('file_hash');
            $table->index('uploaded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_documents');
    }
};
