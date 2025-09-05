<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            // Add missing essential fields
            $table->string('state')->nullable()->after('current_location');
            $table->string('city')->nullable()->after('state');
            $table->decimal('commission_rate', 5, 2)->default(10.00)->after('total_earnings');
            $table->integer('strikes_count')->default(0)->after('rating');
            $table->timestamp('last_active_at')->nullable()->after('service_areas');
            $table->json('delivery_zones')->nullable()->after('service_areas');
            $table->enum('vehicle_status', ['available', 'busy', 'maintenance', 'offline'])
                  ->default('available')->after('vehicle_type');
            $table->decimal('current_capacity_used', 8, 2)->default(0)->after('vehicle_status');
            $table->decimal('max_capacity', 8, 2)->default(50.00)->after('current_capacity_used');
            $table->timestamp('suspended_at')->nullable()->after('last_active_at');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
            $table->timestamp('deleted_at')->nullable(); // Soft deletes
            
            // Add performance tracking fields
            $table->integer('returns_count')->default(0)->after('successful_deliveries');
            $table->integer('complaints_count')->default(0)->after('returns_count');
            $table->decimal('average_delivery_time', 8, 2)->nullable()->after('complaints_count');
            $table->json('performance_metrics')->nullable()->after('average_delivery_time');
            
            // Add indexes for performance
            $table->index(['status', 'state', 'city']);
            $table->index(['rating', 'successful_deliveries']);
            $table->index('last_active_at');
            $table->index('vehicle_status');
            $table->index('deleted_at');
            $table->index('strikes_count');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->dropColumn([
                'state', 'city', 'commission_rate', 'strikes_count',
                'last_active_at', 'delivery_zones', 'vehicle_status',
                'current_capacity_used', 'max_capacity', 'suspended_at',
                'suspension_reason', 'deleted_at', 'returns_count',
                'complaints_count', 'average_delivery_time', 'performance_metrics'
            ]);
        });
    }
};
