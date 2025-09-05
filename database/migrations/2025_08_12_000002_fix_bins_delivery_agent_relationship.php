<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            // Add proper foreign key for delivery agent
            $table->unsignedBigInteger('delivery_agent_id')->nullable()->after('zoho_warehouse_id');
            
            // Rename and improve existing columns
            $table->renameColumn('max_capacity', 'capacity');
            $table->integer('current_stock_count')->default(0)->after('capacity');
            $table->boolean('is_active')->default(true)->after('status');
            $table->json('location_coordinates')->nullable()->after('location');
            $table->string('bin_type')->default('delivery_agent')->after('type');
            $table->timestamp('last_inventory_update')->nullable()->after('metadata');
            $table->decimal('utilization_rate', 5, 2)->default(0)->after('current_stock_count');
            $table->timestamp('deleted_at')->nullable(); // Soft deletes
            
            // Add indexes
            $table->index(['delivery_agent_id', 'status']);
            $table->index(['state', 'is_active']);
            $table->index('bin_type');
            $table->index('utilization_rate');
            $table->index('deleted_at');
            
            // Add foreign key constraint
            $table->foreign('delivery_agent_id')
                  ->references('id')
                  ->on('delivery_agents')
                  ->onDelete('set null');
        });
        
        // Update existing bins to link with delivery agents if assigned_to_da exists
        DB::statement("
            UPDATE bins 
            SET delivery_agent_id = (
                SELECT id FROM delivery_agents 
                WHERE delivery_agents.da_code = bins.assigned_to_da
                LIMIT 1
            ) 
            WHERE assigned_to_da IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->dropForeign(['delivery_agent_id']);
            $table->dropColumn([
                'delivery_agent_id', 'current_stock_count', 'is_active',
                'location_coordinates', 'bin_type', 'last_inventory_update',
                'utilization_rate', 'deleted_at'
            ]);
            $table->renameColumn('capacity', 'max_capacity');
        });
    }
};
