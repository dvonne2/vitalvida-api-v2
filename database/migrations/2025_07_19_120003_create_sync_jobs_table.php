<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('action', ['create', 'update', 'delete', 'sync'])->default('sync');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->index(['device_id', 'status']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_jobs');
    }
}; 