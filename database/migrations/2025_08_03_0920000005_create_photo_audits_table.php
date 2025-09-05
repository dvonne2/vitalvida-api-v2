<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('photo_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained();
            $table->date('audit_date');
            $table->string('photo_url')->nullable();
            $table->timestamp('photo_uploaded_at')->nullable();
            $table->integer('da_claimed_shampoo')->nullable();
            $table->integer('da_claimed_pomade')->nullable();
            $table->integer('da_claimed_conditioner')->nullable();
            $table->integer('im_counted_shampoo')->nullable();
            $table->integer('im_counted_pomade')->nullable();
            $table->integer('im_counted_conditioner')->nullable();
            $table->integer('zoho_recorded_shampoo')->nullable();
            $table->integer('zoho_recorded_pomade')->nullable();
            $table->integer('zoho_recorded_conditioner')->nullable();
            $table->boolean('is_match')->nullable();
            $table->enum('status', ['pending_photo', 'pending_im_review', 'completed', 'flagged']);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['delivery_agent_id', 'audit_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('photo_audits');
    }
};
