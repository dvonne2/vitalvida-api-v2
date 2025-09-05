<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('system_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained();
            $table->enum('type', ['restock', 'transfer', 'audit', 'return']);
            $table->string('priority'); // critical, high, medium, low
            $table->text('message');
            $table->json('action_data'); // specific action details
            $table->enum('status', ['pending', 'executed', 'ignored', 'expired']);
            $table->foreignId('assigned_to')->constrained('users'); // IM
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('system_recommendations');
    }
};
