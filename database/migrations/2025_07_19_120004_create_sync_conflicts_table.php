<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->json('client_data');
            $table->json('server_data');
            $table->enum('conflict_type', ['version_mismatch', 'data_conflict', 'deletion_conflict'])->default('version_mismatch');
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->enum('resolution', ['use_server', 'use_client', 'merge'])->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();

            $table->index(['device_id', 'status']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
    }
}; 