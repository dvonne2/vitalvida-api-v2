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
        // VitalVida Suppliers Table
        Schema::create('vitalvida_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('Nigeria');
            $table->string('payment_terms')->nullable();
            $table->decimal('delivery_rating', 2, 1)->default(0);
            $table->decimal('quality_rating', 2, 1)->default(0);
            $table->decimal('overall_rating', 2, 1)->default(0);
            $table->integer('total_orders')->default(0);
            $table->integer('active_orders')->default(0);
            $table->json('bank_details')->nullable();
            $table->string('tax_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // VitalVida Products Table
        Schema::create('vitalvida_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('category');
            $table->text('description')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->integer('stock_level')->default(0);
            $table->integer('min_stock')->default(0);
            $table->integer('max_stock')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('vitalvida_suppliers');
            $table->date('expiry_date')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('barcode')->nullable();
            $table->enum('status', ['In Stock', 'Low Stock', 'Out of Stock'])->default('In Stock');
            $table->string('location')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('dimensions')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // VitalVida Delivery Agents Table
        Schema::create('vitalvida_delivery_agents', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->unique();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('location');
            $table->text('address');
            $table->enum('status', ['Available', 'On Delivery', 'Offline', 'Suspended'])->default('Available');
            $table->decimal('rating', 2, 1)->default(0);
            $table->integer('total_deliveries')->default(0);
            $table->integer('completed_deliveries')->default(0);
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->decimal('stock_value', 12, 2)->default(0);
            $table->integer('pending_orders')->default(0);
            $table->string('vehicle_type')->nullable();
            $table->string('license_number')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->date('hire_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // VitalVida Delivery Agent Products Table
        Schema::create('vitalvida_delivery_agent_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained('vitalvida_delivery_agents');
            $table->foreignId('product_id')->constrained('vitalvida_products');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_value', 12, 2);
            $table->datetime('assigned_date');
            $table->enum('status', ['assigned', 'delivered', 'returned'])->default('assigned');
            $table->timestamps();
        });

        // VitalVida Stock Transfers Table
        Schema::create('vitalvida_stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_id')->unique();
            $table->foreignId('product_id')->constrained('vitalvida_products');
            $table->foreignId('from_agent_id')->nullable()->constrained('vitalvida_delivery_agents');
            $table->foreignId('to_agent_id')->constrained('vitalvida_delivery_agents');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_value', 12, 2);
            $table->enum('status', ['Pending', 'In Transit', 'Completed', 'Failed'])->default('Pending');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->string('requested_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamps();
        });

        // VitalVida Audit Flags Table
        Schema::create('vitalvida_audit_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained('vitalvida_delivery_agents');
            $table->foreignId('product_id')->nullable()->constrained('vitalvida_products');
            $table->enum('flag_type', ['inventory_discrepancy', 'delivery_issue', 'payment_mismatch', 'behavior_concern']);
            $table->enum('priority', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('MEDIUM');
            $table->text('issue_description');
            $table->integer('expected_quantity')->nullable();
            $table->integer('reported_quantity')->nullable();
            $table->integer('actual_quantity')->nullable();
            $table->decimal('discrepancy_amount', 10, 2)->nullable();
            $table->enum('status', ['active', 'investigating', 'resolved'])->default('active');
            $table->string('flagged_by')->nullable();
            $table->string('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->datetime('resolved_at')->nullable();
            $table->timestamps();
        });

        // VitalVida Purchase Orders Table
        Schema::create('vitalvida_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained('vitalvida_suppliers');
            $table->enum('status', ['Draft', 'Pending', 'Approved', 'Ordered', 'Received', 'Cancelled'])->default('Draft');
            $table->decimal('total_amount', 12, 2);
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamps();
        });

        // VitalVida Purchase Order Items Table
        Schema::create('vitalvida_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('vitalvida_purchase_orders');
            $table->foreignId('product_id')->constrained('vitalvida_products');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vitalvida_purchase_order_items');
        Schema::dropIfExists('vitalvida_purchase_orders');
        Schema::dropIfExists('vitalvida_audit_flags');
        Schema::dropIfExists('vitalvida_stock_transfers');
        Schema::dropIfExists('vitalvida_delivery_agent_products');
        Schema::dropIfExists('vitalvida_delivery_agents');
        Schema::dropIfExists('vitalvida_products');
        Schema::dropIfExists('vitalvida_suppliers');
    }
};
