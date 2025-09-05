<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id(); // This creates auto-incrementing primary key
            $table->integer('product_id');
            $table->integer('from_bin_id');
            $table->integer('to_bin_id');
            $table->decimal('quantity', 10, 2);
            $table->string('movement_type');
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_movements');
    }
};
