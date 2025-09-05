<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('marketing_automation_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sequence_id');
            $table->uuid('customer_id');
            $table->integer('current_step')->default(0);
            $table->enum('status', ['active', 'completed', 'failed', 'paused'])->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('execution_data')->nullable();
            $table->uuid('company_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('sequence_id')->references('id')->on('marketing_automation_sequences')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->index(['company_id', 'status']);
            $table->index(['sequence_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketing_automation_executions');
    }
};
