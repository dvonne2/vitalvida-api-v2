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
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name'); // Moniepoint, First Bank, etc.
            $table->string('account_number');
            $table->string('account_name');
            $table->string('account_code'); // LIO-FGH, MAR-FGH, etc.
            $table->enum('wallet_type', ['marketing', 'opex', 'inventory', 'profit', 'bonus', 'tax', 'main']);
            $table->decimal('allocation_percentage', 5, 2); // 40%, 25%, etc.
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->enum('status', ['active', 'locked', 'restricted'])->default('active');
            $table->text('purpose_description');
            $table->string('api_key')->nullable(); // For Moniepoint API integration
            $table->string('webhook_url')->nullable();
            $table->json('transaction_limits')->nullable(); // Daily/monthly limits
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users');
            $table->index(['wallet_type', 'status']);
            $table->index(['bank_name', 'account_number']);
            $table->unique(['bank_name', 'account_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
