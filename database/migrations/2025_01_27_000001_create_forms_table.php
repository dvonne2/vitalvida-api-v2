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
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('header_text')->default('Place Your Order');
            $table->text('sub_header_text')->default('Only Serious Buyers Should Fill This Form');
            $table->json('fields_config'); // Dynamic form fields configuration
            $table->json('products'); // Product options with prices
            $table->json('payment_methods'); // Payment method options
            $table->json('delivery_options'); // Delivery options with pricing
            $table->string('thank_you_message')->default('Thanks! Your order has been received. One of our team members will call you shortly to confirm.');
            $table->string('background_color')->default('#f8f9fa');
            $table->string('primary_color')->default('#DAA520');
            $table->string('font_family')->default('Montserrat');
            $table->string('headline_font')->default('Playfair Display');
            $table->boolean('show_country_code')->default(true);
            $table->boolean('require_email')->default(false);
            $table->boolean('honeypot_enabled')->default(true);
            $table->string('webhook_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('total_submissions')->default(0);
            $table->timestamp('last_submission_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
}; 