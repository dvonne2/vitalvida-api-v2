<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixBinsTableNullableZohoFields extends Migration
{
    public function up()
    {
        Schema::table('bins', function (Blueprint $table) {
            // Make Zoho fields nullable since they'll be populated during sync
            $table->string('zoho_storage_id')->nullable()->change();
            $table->string('zoho_warehouse_id')->nullable()->change();
            $table->string('zoho_zone_id')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('bins', function (Blueprint $table) {
            $table->string('zoho_storage_id')->nullable(false)->change();
            $table->string('zoho_warehouse_id')->nullable(false)->change();
            $table->string('zoho_zone_id')->nullable(false)->change();
        });
    }
}
