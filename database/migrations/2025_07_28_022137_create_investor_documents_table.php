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
        Schema::create('investor_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('document_categories')->onDelete('cascade');
            $table->enum('status', ['ready', 'in_progress', 'not_ready'])->default('not_ready');
            $table->enum('completion_status', ['complete', 'incomplete', 'pending'])->default('incomplete');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_type')->nullable();
            $table->integer('file_size')->nullable(); // in bytes
            $table->json('access_permissions')->nullable(); // Which investor roles can access
            $table->date('due_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->boolean('is_required')->default(true);
            $table->integer('priority')->default(1); // 1=low, 2=medium, 3=high
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['status', 'completion_status']);
            $table->index('category_id');
            $table->index('due_date');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investor_documents');
    }
};
