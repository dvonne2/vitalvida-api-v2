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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->foreignId('head_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('budget', 15, 2)->default(0);
            $table->decimal('target_revenue', 15, 2)->default(0);
            $table->decimal('current_revenue', 15, 2)->default(0);
            $table->integer('employee_count')->default(0);
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('color', 7)->nullable(); // Hex color code
            $table->string('icon', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['status', 'code']);
            $table->index('head_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
