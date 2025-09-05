<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // IM DAILY LOGS - Create only if missing
        if (!Schema::hasTable('im_daily_logs')) {
            Schema::create('im_daily_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
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

        // SYSTEM RECOMMENDATIONS - Create only if missing
        if (!Schema::hasTable('system_recommendations')) {
            Schema::create('system_recommendations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_agent_id');
                $table->string('type'); // restock, transfer, audit, return
                $table->string('priority'); // critical, high, medium, low
                $table->text('message');
                $table->json('action_data');
                $table->string('status')->default('pending'); // pending, executed, ignored, expired
                $table->unsignedBigInteger('assigned_to');
                $table->timestamp('executed_at')->nullable();
                $table->timestamps();
            });
        }

        // PHOTO AUDITS - Create only if missing
        if (!Schema::hasTable('photo_audits')) {
            Schema::create('photo_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_agent_id');
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
                $table->string('status')->default('pending_photo');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['delivery_agent_id', 'audit_date']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('photo_audits');
        Schema::dropIfExists('system_recommendations');
        Schema::dropIfExists('im_daily_logs');
    }
};
