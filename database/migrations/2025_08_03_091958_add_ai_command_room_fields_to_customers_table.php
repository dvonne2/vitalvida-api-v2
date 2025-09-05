<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            // AI Command Room additions
            $table->string('whatsapp_id')->nullable()->after('email');
            $table->string('meta_pixel_id')->nullable()->after('whatsapp_id');
            $table->decimal('total_spent', 10, 2)->default(0)->after('meta_pixel_id');
            $table->integer('orders_count')->default(0)->after('total_spent');
            $table->timestamp('last_purchase_date')->nullable()->after('orders_count');
            $table->decimal('churn_probability', 3, 2)->default(0)->after('last_purchase_date');
            $table->decimal('lifetime_value_prediction', 10, 2)->default(0)->after('churn_probability');
            $table->json('preferred_contact_time')->nullable()->after('lifetime_value_prediction');
            $table->string('persona_tag')->nullable()->after('preferred_contact_time');
            $table->string('acquisition_source')->nullable()->after('persona_tag');
            $table->integer('age')->nullable()->after('acquisition_source');
            
            // Indexes for performance
            $table->index(['churn_probability', 'last_purchase_date']);
            $table->index(['total_spent', 'orders_count']);
            $table->index(['lifetime_value_prediction', 'churn_probability']);
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['churn_probability', 'last_purchase_date']);
            $table->dropIndex(['total_spent', 'orders_count']);
            $table->dropIndex(['lifetime_value_prediction', 'churn_probability']);
            
            $table->dropColumn([
                'whatsapp_id',
                'meta_pixel_id',
                'total_spent',
                'orders_count',
                'last_purchase_date',
                'churn_probability',
                'lifetime_value_prediction',
                'preferred_contact_time',
                'persona_tag',
                'acquisition_source',
                'age'
            ]);
        });
    }
}; 