<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zoho_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type');
            $table->string('operation_id')->nullable();
            $table->string('zoho_endpoint');
            $table->string('http_method');
            $table->json('request_payload')->nullable();
            $table->json('response_data')->nullable();
            $table->integer('response_status_code')->nullable();
            $table->enum('status', ['pending', 'success', 'failed', 'retrying'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['operation_type', 'status']);
            $table->index(['status', 'next_retry_at']);
            $table->index(['operation_id', 'operation_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('zoho_operation_logs');
    }
};
