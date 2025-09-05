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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_code', 10)->unique();
            $table->string('account_name');
            $table->enum('account_type', ['Asset', 'Liability', 'Income', 'Expense', 'Equity']);
            $table->string('reporting_group')->nullable();
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->boolean('is_locked')->default(false);
            $table->unsignedBigInteger('parent_account_id')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->timestamps();
            
            $table->foreign('parent_account_id')->references('id')->on('chart_of_accounts');
            $table->index(['account_type', 'is_locked']);
            $table->index(['reporting_group', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
