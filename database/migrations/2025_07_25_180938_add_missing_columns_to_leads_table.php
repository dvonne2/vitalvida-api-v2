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
            if (!Schema::hasColumn('leads', 'product')) {
                $table->string('product')->nullable()->after('customer_email');
            }
            if (!Schema::hasColumn('leads', 'promo_code')) {
                $table->string('promo_code')->nullable()->after('product');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['product', 'promo_code']);
        });
    }
};
