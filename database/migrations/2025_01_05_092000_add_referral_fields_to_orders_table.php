<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->text('referral_ref_token')->nullable()->after('delivery_fee_naira');
            $table->boolean('referral_friend_discount_applied')->default(false)->after('referral_ref_token');
            $table->boolean('referral_free_delivery_applied')->default(false)->after('referral_friend_discount_applied');
            $table->boolean('referrer_reward_applied')->default(false)->after('referral_free_delivery_applied');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'referral_ref_token',
                'referral_friend_discount_applied',
                'referral_free_delivery_applied',
                'referrer_reward_applied'
            ]);
        });
    }
};
