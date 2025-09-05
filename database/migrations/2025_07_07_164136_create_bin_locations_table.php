<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBinLocationsTable extends Migration
{
    public function up()
    {
        Schema::create('bin_locations', function (Blueprint $table) {
            $table->id();
            $table->string('bin_id')->unique();
            $table->string('bin_name');
            $table->string('warehouse_id');
            $table->string('zone')->nullable();
            $table->string('aisle')->nullable();
            $table->string('rack')->nullable();
            $table->string('shelf')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('restrictions')->nullable();
            $table->timestamps();
            
            $table->index(['warehouse_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bin_locations');
    }
}
