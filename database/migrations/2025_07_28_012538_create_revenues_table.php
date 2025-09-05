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
        Schema::create('revenues', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('order_revenue', 15, 2)->default(0);
            $table->decimal('delivery_revenue', 15, 2)->default(0);
            $table->decimal('service_revenue', 15, 2)->default(0);
            $table->decimal('other_revenue', 15, 2)->default(0);
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('set null');
            $table->string('source', 100)->nullable();
            $table->string('currency', 3)->default('NGN');
            $table->decimal('exchange_rate', 10, 4)->default(1.0000);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['date', 'department_id']);
            $table->index('source');
            $table->unique(['date', 'department_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenues');
    }
};
