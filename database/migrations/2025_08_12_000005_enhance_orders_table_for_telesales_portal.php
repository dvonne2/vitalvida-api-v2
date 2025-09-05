<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Added this import for DB facade

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add only missing Telesales Portal specific fields
            if (!Schema::hasColumn('orders', 'call_status')) {
                $table->enum('call_status', ['pending', 'called', 'confirmed', 'not_interested', 'callback'])->default('pending')->after('status');
            }
            if (!Schema::hasColumn('orders', 'commission')) {
                $table->decimal('commission', 10, 2)->default(0)->after('total_amount');
            }
            if (!Schema::hasColumn('orders', 'customer_location')) {
                $table->string('customer_location')->nullable()->after('delivery_address');
            }
            if (!Schema::hasColumn('orders', 'product_details')) {
                $table->json('product_details')->nullable()->after('items'); // {shampoo: 1, pomade: 1, etc}
            }
            if (!Schema::hasColumn('orders', 'delivery_status')) {
                $table->enum('delivery_status', ['pending', 'assigned', 'in_transit', 'delivered', 'failed'])->default('pending')->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'otp_generated_at')) {
                $table->timestamp('otp_generated_at')->nullable()->after('otp_code');
            }
            if (!Schema::hasColumn('orders', 'otp_used_at')) {
                $table->timestamp('otp_used_at')->nullable()->after('otp_generated_at');
            }
            if (!Schema::hasColumn('orders', 'kemi_chat_log')) {
                $table->json('kemi_chat_log')->nullable()->after('notes'); // Store AI conversation
            }
            if (!Schema::hasColumn('orders', 'notes')) {
                $table->text('notes')->nullable()->after('kemi_chat_log');
            }
            
            // Indexes for performance (only if columns exist)
            if (Schema::hasColumn('orders', 'call_status')) {
                $table->index(['call_status', 'created_at']);
            }
            if (Schema::hasColumn('orders', 'delivery_status')) {
                $table->index(['delivery_status', 'created_at']);
            }
            if (Schema::hasColumn('orders', 'telesales_agent_id')) {
                $table->index(['telesales_agent_id', 'call_status']);
            }
            if (Schema::hasColumn('orders', 'otp_code')) {
                $table->index('otp_code');
            }
        });

        // Update existing payment_status values to match new enum
        DB::statement("UPDATE orders SET payment_status = 'awaiting' WHERE payment_status = 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['telesales_agent_id']);
            $table->dropIndex(['call_status', 'created_at']);
            $table->dropIndex(['delivery_status', 'created_at']);
            $table->dropIndex(['telesales_agent_id', 'call_status']);
            $table->dropIndex('otp_code');
            
            $table->dropColumn([
                'telesales_agent_id',
                'call_status',
                'commission',
                'customer_location',
                'product_details',
                'delivery_status',
                'otp_code',
                'otp_generated_at',
                'otp_used_at',
                'delivered_at',
                'assigned_at',
                'kemi_chat_log'
            ]);
        });
    }
};
