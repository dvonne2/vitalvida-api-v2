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
        Schema::create('ucx_single_source_truth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('data_type'); // profile, preferences, behavior, context
            $table->json('unified_data'); // The actual unified data
            $table->json('data_sources'); // Which systems contributed
            $table->timestamp('last_sync'); // When data was last updated
            $table->json('sync_conflicts')->nullable(); // Any data conflicts found
            $table->boolean('is_current')->default(true); // Is this the current version
            $table->json('access_permissions'); // Who can access this data
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['customer_id', 'data_type', 'company_id']);
            $table->index(['last_sync', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ucx_single_source_truth');
    }
};
