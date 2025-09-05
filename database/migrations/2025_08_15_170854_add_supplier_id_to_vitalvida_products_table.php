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
        Schema::table('vitalvida_products', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained('vitalvida_suppliers')->onDelete('set null');
            $table->decimal('supplier_price', 10, 2)->nullable(); // Cost from supplier
            $table->string('supplier_product_code')->nullable(); // Supplier's internal code
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vitalvida_products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['supplier_id', 'supplier_price', 'supplier_product_code']);
        });
    }
};
