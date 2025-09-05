<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('im_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // IM user
            $table->date('log_date');
            $table->time('login_time')->nullable();
            $table->boolean('completed_da_review')->default(false);
            $table->integer('das_reviewed_count')->default(0);
            $table->integer('recommendations_executed')->default(0);
            $table->decimal('penalty_amount', 10, 2)->default(0);
            $table->decimal('bonus_amount', 10, 2)->default(0);
            $table->timestamps();
            
            $table->unique(['user_id', 'log_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('im_daily_logs');
    }
};
