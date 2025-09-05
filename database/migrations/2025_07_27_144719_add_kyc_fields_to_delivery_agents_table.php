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
        Schema::table('delivery_agents', function (Blueprint $table) {
            // KYC Portal specific fields
            $table->string('agent_id', 10)->unique()->nullable()->after('id'); // DA001, DA002, etc.
            $table->string('full_name', 255)->nullable()->after('agent_id');
            $table->string('phone_number', 20)->nullable()->after('full_name');
            $table->string('whatsapp_number', 20)->nullable()->after('phone_number');
            $table->string('email', 255)->nullable()->after('whatsapp_number');
            
            // KYC Application status
            $table->enum('kyc_status', ['pending', 'approved', 'rejected', 'waiting_guarantors'])->default('pending')->after('status');
            $table->decimal('ai_score', 5, 2)->default(0)->after('kyc_status');
            $table->integer('application_step')->default(1)->after('ai_score');
            
            // Application tracking
            $table->timestamp('application_submitted_at')->nullable()->after('application_step');
            $table->timestamp('kyc_approved_at')->nullable()->after('application_submitted_at');
            $table->timestamp('kyc_rejected_at')->nullable()->after('kyc_approved_at');
            $table->text('rejection_reason')->nullable()->after('kyc_rejected_at');
            
            // Auto-approval tracking
            $table->boolean('auto_approved')->default(false)->after('rejection_reason');
            $table->timestamp('auto_approved_at')->nullable()->after('auto_approved');
            
            // Indexes for performance
            $table->index(['kyc_status', 'application_step']);
            $table->index(['ai_score', 'kyc_status']);
            $table->index('agent_id');
            $table->index('auto_approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $table->dropIndex(['kyc_status', 'application_step']);
            $table->dropIndex(['ai_score', 'kyc_status']);
            $table->dropIndex(['agent_id']);
            $table->dropIndex(['auto_approved']);
            
            $table->dropColumn([
                'agent_id', 'full_name', 'phone_number', 'whatsapp_number', 'email',
                'kyc_status', 'ai_score', 'application_step', 'application_submitted_at',
                'kyc_approved_at', 'kyc_rejected_at', 'rejection_reason',
                'auto_approved', 'auto_approved_at'
            ]);
        });
    }
};
