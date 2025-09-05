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
        Schema::table('leads', function (Blueprint $table) {
            $table->unsignedBigInteger('form_id')->nullable()->after('id');
            $table->string('product')->nullable()->after('customer_email');
            $table->string('promo_code')->nullable()->after('product');
            $table->string('payment_method')->nullable()->after('promo_code');
            $table->string('delivery_preference')->nullable()->after('payment_method');
            $table->decimal('delivery_cost', 8, 2)->default(0)->after('delivery_preference');
            
            $table->foreign('form_id')->references('id')->on('forms')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['form_id']);
            $table->dropColumn(['form_id', 'product', 'promo_code', 'payment_method', 'delivery_preference', 'delivery_cost']);
        });
    }
}; 