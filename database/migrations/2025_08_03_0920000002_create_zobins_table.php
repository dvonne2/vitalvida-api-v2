<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('zobins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained();
            $table->string('zoho_storage_id')->nullable();
            $table->string('zoho_warehouse_id');
            $table->integer('shampoo_count')->default(0);
            $table->integer('pomade_count')->default(0);
            $table->integer('conditioner_count')->default(0);
            $table->timestamp('last_updated');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('zobins');
    }
};
