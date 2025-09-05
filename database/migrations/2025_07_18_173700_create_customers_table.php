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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id', 50)->unique(); // VV-CUST-001
            $table->string('name');
            $table->string('phone', 20)->unique();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('lga', 100)->nullable(); // Local Government Area
            $table->enum('customer_type', ['individual', 'business'])->default('individual');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('zoho_contact_id')->nullable(); // Zoho CRM ID
            $table->decimal('lifetime_value', 10, 2)->default(0.00);
            $table->integer('total_orders')->default(0);
            $table->date('last_order_date')->nullable();
            $table->json('preferences')->nullable(); // Customer preferences
            $table->timestamps();

            $table->index('customer_id');
            $table->index('phone');
            $table->index('email');
            $table->index('status');
            $table->index('zoho_contact_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
