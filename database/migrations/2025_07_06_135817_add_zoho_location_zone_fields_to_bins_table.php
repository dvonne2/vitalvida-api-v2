<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->string('zoho_location_id')->nullable()->after('zoho_warehouse_id');
            $table->string('zoho_zone_id')->nullable()->after('zoho_location_id');
            $table->string('zoho_bin_id')->nullable()->after('zoho_zone_id');
            
            // Add indexes for better performance
            $table->index(['zoho_location_id', 'zoho_zone_id']);
            $table->index('zoho_bin_id');
        });
    }

    public function down()
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->dropIndex(['zoho_location_id', 'zoho_zone_id']);
            $table->dropIndex(['zoho_bin_id']);
            $table->dropColumn(['zoho_location_id', 'zoho_zone_id', 'zoho_bin_id']);
        });
    }
};
