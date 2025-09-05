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
        Schema::create('vitalvida_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_code')->unique(); // SUP001, SUP002
            $table->string('company_name');
            $table->string('contact_person');
            $table->string('phone');
            $table->string('email')->unique();
            $table->text('business_address');
            $table->string('website')->nullable();
            $table->json('products_supplied'); // Array of product categories
            $table->decimal('rating', 3, 2)->default(0.00); // 0.00 to 5.00
            $table->integer('total_orders')->default(0);
            $table->decimal('total_purchase_value', 15, 2)->default(0.00);
            $table->string('payment_terms')->default('30 Days'); // 30 Days, 60 Days, etc.
            $table->string('delivery_time')->default('1-2 Days');
            $table->enum('status', ['Active', 'Inactive', 'Pending', 'Suspended'])->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_suppliers');
    }
};
