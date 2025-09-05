<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('low_stock_threshold')->default(10)->after('price');
            $table->boolean('is_low_stock')->default(false)->after('low_stock_threshold');
            $table->datetime('last_stock_check')->nullable()->after('is_low_stock');
            
            $table->index(['is_low_stock', 'last_stock_check']);
            $table->index('low_stock_threshold');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_low_stock', 'last_stock_check']);
            $table->dropIndex(['low_stock_threshold']);
            $table->dropColumn([
                'low_stock_threshold',
                'is_low_stock',
                'last_stock_check'
            ]);
        });
    }
};
