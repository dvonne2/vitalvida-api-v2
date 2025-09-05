<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add GM Portal specific fields
            $table->enum('source', ['meta_ad', 'instagram', 'whatsapp', 'repeat_buyer', 'manual', 'referral', 'organic'])->nullable()->after('customer_email');
            $table->foreignId('assigned_telesales_id')->nullable()->constrained('users')->onDelete('set null')->after('assigned_da_id');
            $table->string('state', 100)->nullable()->after('delivery_address');
            $table->string('otp_code', 10)->nullable()->after('delivery_otp');
            $table->string('delivery_photo_path')->nullable()->after('otp_verified_at');
            $table->timestamp('delivered_at')->nullable()->after('delivery_notes');
            $table->json('fraud_flags')->nullable()->after('delivered_at');
            $table->boolean('is_ghosted')->default(false)->after('fraud_flags');
            $table->timestamp('ghosted_at')->nullable()->after('is_ghosted');
            $table->text('ghost_reason')->nullable()->after('ghosted_at');
            
            // Indexes
            $table->index(['source', 'created_at']);
            $table->index(['assigned_telesales_id', 'status']);
            $table->index(['state', 'status']);
            $table->index('is_ghosted');
            $table->index('delivered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['assigned_telesales_id']);
            $table->dropIndex(['source', 'created_at']);
            $table->dropIndex(['assigned_telesales_id', 'status']);
            $table->dropIndex(['state', 'status']);
            $table->dropIndex(['is_ghosted']);
            $table->dropIndex(['delivered_at']);
            
            $table->dropColumn([
                'source',
                'assigned_telesales_id',
                'state',
                'otp_code',
                'delivery_photo_path',
                'delivered_at',
                'fraud_flags',
                'is_ghosted',
                'ghosted_at',
                'ghost_reason'
            ]);
        });
    }
};
