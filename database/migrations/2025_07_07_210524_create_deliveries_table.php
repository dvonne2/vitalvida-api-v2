<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeliveriesTable extends Migration
{
    public function up()
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->index();
            $table->string('delivery_location')->nullable();
            $table->string('recipient_name')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->unsignedBigInteger('confirmed_by');
            $table->timestamp('confirmed_at');
            $table->boolean('otp_verified')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('deliveries');
    }
}
