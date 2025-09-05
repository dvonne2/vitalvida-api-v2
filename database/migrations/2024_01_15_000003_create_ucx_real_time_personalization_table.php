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
        Schema::create('ucx_real_time_personalization', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('trigger_event'); // What triggered personalization
            $table->json('customer_context_at_trigger'); // Their state when triggered
            $table->json('personalization_applied'); // What we personalized
            $table->string('channel_applied'); // Where personalization happened
            $table->json('decision_factors'); // Why we made this choice
            $table->decimal('relevancy_score', 5, 2); // How relevant was it
            $table->json('customer_response')->nullable(); // How customer reacted
            $table->boolean('was_effective')->nullable(); // Did it work
            $table->timestamp('applied_at');
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['customer_id', 'applied_at']);
            $table->index(['relevancy_score', 'was_effective']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ucx_real_time_personalization');
    }
};
