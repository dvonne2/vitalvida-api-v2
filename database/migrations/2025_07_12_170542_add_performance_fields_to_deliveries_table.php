<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries', 'delivery_photo')) {
                $table->string('delivery_photo')->nullable()->after('delivery_notes');
            }
            if (!Schema::hasColumn('deliveries', 'photo_verified')) {
                $table->boolean('photo_verified')->default(false)->after('delivery_photo');
            }
            if (!Schema::hasColumn('deliveries', 'photo_verified_by')) {
                $table->unsignedBigInteger('photo_verified_by')->nullable()->after('photo_verified');
            }
            if (!Schema::hasColumn('deliveries', 'photo_verified_at')) {
                $table->timestamp('photo_verified_at')->nullable()->after('photo_verified_by');
            }
            if (!Schema::hasColumn('deliveries', 'delivery_status')) {
                $table->enum('delivery_status', ['pending', 'in_progress', 'delivered', 'failed', 'returned'])->default('pending')->after('otp_verified');
            }
            if (!Schema::hasColumn('deliveries', 'agent_id')) {
                $table->unsignedBigInteger('agent_id')->nullable()->after('confirmed_by');
            }
            if (!Schema::hasColumn('deliveries', 'completion_time')) {
                $table->timestamp('completion_time')->nullable()->after('confirmed_at');
            }
            if (!Schema::hasColumn('deliveries', 'customer_rating')) {
                $table->decimal('customer_rating', 2, 1)->nullable()->after('completion_time');
            }
            if (!Schema::hasColumn('deliveries', 'customer_feedback')) {
                $table->text('customer_feedback')->nullable()->after('customer_rating');
            }
            if (!Schema::hasColumn('deliveries', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_photo', 'photo_verified', 'photo_verified_by', 'photo_verified_at',
                'delivery_status', 'agent_id', 'completion_time', 'customer_rating', 'customer_feedback'
            ]);
            $table->dropSoftDeletes();
        });
    }
};
