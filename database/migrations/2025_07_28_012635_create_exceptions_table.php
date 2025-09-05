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
        Schema::create('exceptions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description');
            $table->string('type', 50);
            $table->string('category', 50);
            $table->enum('severity', ['critical', 'high', 'medium', 'low'])->default('medium');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->string('exceptionable_type', 100)->nullable();
            $table->unsignedBigInteger('exceptionable_id')->nullable();
            $table->enum('status', ['active', 'assigned', 'in_progress', 'resolved'])->default('active');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('resolution_notes')->nullable();
            $table->boolean('auto_generated')->default(false);
            $table->string('source', 100)->default('system');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['status', 'severity']);
            $table->index(['department_id', 'status']);
            $table->index(['exceptionable_type', 'exceptionable_id']);
            $table->index(['assigned_to', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exceptions');
    }
};
