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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->string('zoho_po_id')->nullable(); // Link to Zoho Inventory
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->enum('status', ['draft', 'pending', 'approved', 'in_production', 'completed', 'cancelled'])->default('draft');
            $table->json('items'); // Store PO items as JSON
            $table->decimal('total_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->date('expected_delivery_date');
            $table->date('actual_delivery_date')->nullable();
            $table->enum('qc_status', ['pending', 'passed', 'failed'])->default('pending');
            $table->text('qc_notes')->nullable();
            $table->foreignId('qc_checked_by')->nullable()->constrained('users');
            $table->timestamp('handover_date')->nullable();
            $table->foreignId('handover_to')->nullable()->constrained('users');
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'created_at']);
            $table->index('zoho_po_id');
            $table->index('qc_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
}; 