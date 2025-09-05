<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('order_otps', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->string('otp_code', 6);
            $table->integer('attempt_count')->default(0);
            $table->integer('resend_count')->default(0);
            $table->timestamp('expires_at');
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            
            $table->index(['order_number', 'otp_code']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_otps');
    }
};
