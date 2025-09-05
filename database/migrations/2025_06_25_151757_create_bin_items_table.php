<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bin_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bin_id')->constrained()->onDelete('cascade');
            $table->string('item_id');
            $table->string('item_name');
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->decimal('cost_per_unit', 10, 2)->default(0);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->unique(['bin_id', 'item_id']);
            $table->index(['item_id', 'quantity']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bin_items');
    }
};
