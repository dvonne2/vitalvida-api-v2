<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('marketing_automation_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->uuid('brand_id');
            $table->enum('trigger_type', ['customer_signup', 'purchase', 'abandoned_cart', 'birthday', 'custom_date']);
            $table->json('trigger_conditions')->nullable();
            $table->json('steps');
            $table->json('target_audience')->nullable();
            $table->enum('status', ['draft', 'active', 'paused'])->default('draft');
            $table->timestamp('activated_at')->nullable();
            $table->uuid('company_id');
            $table->uuid('created_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('brand_id')->references('id')->on('marketing_brands')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');

            $table->index(['company_id', 'status']);
            $table->index(['brand_id', 'status']);
            $table->index('trigger_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketing_automation_sequences');
    }
};
