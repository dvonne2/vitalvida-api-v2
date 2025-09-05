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
        Schema::create('logistics_costs', function (Blueprint $table) {
            $table->id();
            $table->enum('transfer_type', ['supplier_to_im', 'im_to_da', 'da_to_da', 'da_to_factory']);
            $table->string('origin_location');
            $table->string('origin_phone');
            $table->string('destination_location');
            $table->string('destination_phone');
            $table->text('items_description');
            $table->integer('quantity');
            $table->string('transport_company');
            $table->string('transport_phone');
            $table->string('storekeeper_phone');
            $table->decimal('total_cost', 10, 2);
            $table->decimal('cost_per_unit', 8, 2);
            $table->decimal('storekeeper_fee', 8, 2);
            $table->decimal('transport_fare', 10, 2);
            $table->string('proof_of_payment_path')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['pending', 'approved', 'paid', 'escalated'])->default('pending');
            $table->timestamps();
            
            $table->index(['status', 'transfer_type']);
            $table->index(['approved_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logistics_costs');
    }
}; 