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
        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 50);
            $table->boolean('customer_payment_received')->default(false);
            $table->string('da_name', 255)->nullable();
            $table->string('da_phone', 20)->nullable();
            $table->decimal('delivery_amount', 10, 2);
            $table->enum('payment_method', ['cash', 'transfer', 'pos', 'online'])->default('cash');
            $table->enum('verification_status', ['pending', '3_way_match', 'mismatch', 'confirmed'])->default('pending');
            $table->enum('zoho_status', ['pending', 'synced', 'error'])->default('pending');
            $table->string('im_says', 255)->nullable();
            $table->string('da_says', 255)->nullable();
            $table->string('zoho_shows', 255)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('accountants')->onDelete('set null');
            $table->timestamp('processed_at')->nullable();
            $table->boolean('receipt_uploaded')->default(false);
            $table->string('receipt_path', 500)->nullable();
            $table->timestamps();
            
            $table->index('order_id');
            $table->index('verification_status');
            $table->index('processed_by');
            $table->index('processed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
}; 