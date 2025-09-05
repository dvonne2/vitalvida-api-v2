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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->unique();
            $table->string('whatsapp_number')->nullable();
            $table->boolean('whatsapp_consent')->default(false);
            $table->string('customer_type')->default('retail'); // retail, wholesale, distributor
            $table->string('location')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->date('last_order_date')->nullable();
            $table->string('source')->nullable(); // referral, social_media, search, etc.
            $table->json('preferences')->nullable();
            $table->string('status')->default('active'); // active, inactive, blocked
            $table->timestamps();
            
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->index(['company_id', 'phone']);
            $table->index(['company_id', 'whatsapp_consent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
