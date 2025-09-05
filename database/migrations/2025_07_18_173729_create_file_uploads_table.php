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
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('file_id', 50)->unique(); // VV-FILE-001
            $table->unsignedBigInteger('uploadable_id'); // ID of related model
            $table->string('uploadable_type'); // Model type (MoneyOutCompliance, LogisticsCost, etc.)
            $table->string('file_name'); // Original filename
            $table->string('file_path'); // Storage path
            $table->string('file_url')->nullable(); // Public URL
            $table->bigInteger('file_size'); // File size in bytes
            $table->string('mime_type', 100);
            $table->string('file_extension', 10);
            $table->enum('file_type', ['proof_of_payment', 'receipt', 'invoice', 'inventory_photo', 'document', 'other'])->default('document');
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->unsignedBigInteger('uploaded_by'); // User who uploaded
            $table->unsignedBigInteger('verified_by')->nullable(); // User who verified
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('hash', 64)->nullable(); // File hash for integrity
            $table->json('metadata')->nullable(); // Additional file metadata
            $table->timestamps();

            $table->index('file_id');
            $table->index(['uploadable_type', 'uploadable_id']);
            $table->index('uploaded_by');
            $table->index('verified_by');
            $table->index('file_type');
            $table->index('status');
            $table->index('mime_type');
            
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_uploads');
    }
};
