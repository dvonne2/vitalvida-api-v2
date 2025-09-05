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
        Schema::create('agent_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('delivery_agents')->onDelete('cascade');
            
            // Guarantor Information
            $table->enum('guarantor_type', ['bank_staff', 'civil_servant', 'business_owner', 'professional'])->notNull();
            $table->string('full_name', 255)->notNull();
            $table->string('email', 255)->notNull();
            $table->string('phone_number', 20)->notNull();
            $table->string('organization', 255)->notNull();
            $table->string('position', 255)->notNull();
            $table->string('employee_id', 50)->nullable();
            
            // Address Information
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            
            // Verification Status
            $table->enum('verification_status', ['pending', 'verified', 'rejected', 'expired'])->default('pending');
            $table->string('verification_code', 10)->nullable();
            $table->timestamp('verification_code_sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Verification Attempts
            $table->integer('verification_attempts')->default(0);
            $table->timestamp('last_verification_attempt')->nullable();
            
            // Relationship Details
            $table->enum('relationship', ['family', 'friend', 'colleague', 'employer', 'other'])->nullable();
            $table->text('relationship_details')->nullable();
            $table->integer('years_known')->nullable();
            
            // Guarantor Score
            $table->decimal('guarantor_score', 5, 2)->default(0);
            $table->boolean('is_primary_guarantor')->default(false);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['agent_id', 'verification_status']);
            $table->index(['verification_code', 'verification_status']);
            $table->index('guarantor_score');
            $table->index('is_primary_guarantor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_guarantors');
    }
};
