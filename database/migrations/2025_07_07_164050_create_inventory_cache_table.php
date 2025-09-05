<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryCacheTable extends Migration
{
    public function up()
    {
        Schema::create('inventory_cache', function (Blueprint $table) {
            $table->id();
            $table->string('item_id');
            $table->string('bin_id');
            $table->string('warehouse_id');
            $table->integer('available_stock')->default(0);
            $table->integer('reserved_stock')->default(0);
            $table->timestamp('last_synced_at');
            $table->timestamps();
            
            $table->unique(['item_id', 'bin_id']);
            $table->index(['item_id', 'available_stock']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_cache');
    }
}
