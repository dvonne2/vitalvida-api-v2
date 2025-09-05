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
        Schema::table('delivery_agents', function (Blueprint $table) {
            // Add Telesales Portal specific fields
            $table->string('territory')->nullable()->after('city');
            $table->enum('status', ['active', 'inactive', 'no_stock'])->change();
            $table->string('zoho_bin_id')->nullable()->after('status'); // Link to Zoho Inventory bin
            $table->json('current_stock')->nullable()->after('zoho_bin_id'); // {shampoo: 1, pomade: 2, conditioner: 4}
            $table->integer('active_orders_count')->default(0)->after('current_stock');
            $table->timestamp('last_stock_sync')->nullable()->after('active_orders_count');
            
            // Indexes for performance
            $table->index(['status', 'territory']);
            $table->index('zoho_bin_id');
            $table->index('active_orders_count');
            $table->index('last_stock_sync');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->dropIndex(['status', 'territory']);
            $table->dropIndex('zoho_bin_id');
            $table->dropIndex('active_orders_count');
            $table->dropIndex('last_stock_sync');
            
            $table->dropColumn([
                'territory',
                'zoho_bin_id',
                'current_stock',
                'active_orders_count',
                'last_stock_sync'
            ]);
        });
    }
};
