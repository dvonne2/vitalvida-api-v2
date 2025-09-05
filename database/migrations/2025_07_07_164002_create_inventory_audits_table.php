<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryAuditsTable extends Migration
{
    public function up()
    {
        Schema::create('inventory_audits', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->index();
            $table->string('item_id');
            $table->string('bin_id');
            $table->integer('quantity_deducted');
            $table->enum('reason', ['package_dispatch', 'order_fulfillment', 'quality_control', 'return_processing']);
            $table->unsignedBigInteger('user_id');
            $table->string('zoho_adjustment_id')->nullable();
            $table->json('zoho_response')->nullable();
            $table->timestamp('deducted_at');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['item_id', 'bin_id']);
            $table->index('deducted_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_audits');
    }
}
