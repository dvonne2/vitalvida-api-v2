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
        Schema::table('escalation_requests', function (Blueprint $table) {
            // Add accountant-specific fields
            $table->string('reference_id', 50)->nullable()->after('threshold_violation_id');
            $table->string('location', 255)->nullable()->after('reference_id');
            $table->json('contact_info')->nullable()->after('business_justification');
            $table->enum('fc_decision', ['pending', 'approved', 'rejected'])->default('pending')->after('status');
            $table->enum('gm_decision', ['pending', 'approved', 'rejected'])->default('pending')->after('fc_decision');
            $table->foreignId('submitted_by')->nullable()->constrained('accountants')->onDelete('cascade')->after('created_by');
            $table->timestamp('fc_reviewed_at')->nullable()->after('final_decision_at');
            $table->timestamp('gm_reviewed_at')->nullable()->after('fc_reviewed_at');
            
            // Add indexes for accountant portal queries
            $table->index('submitted_by');
            $table->index('fc_decision');
            $table->index('gm_decision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('escalation_requests', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropIndex(['submitted_by']);
            $table->dropIndex(['fc_decision']);
            $table->dropIndex(['gm_decision']);
            
            $table->dropColumn([
                'reference_id',
                'location',
                'contact_info',
                'fc_decision',
                'gm_decision',
                'submitted_by',
                'fc_reviewed_at',
                'gm_reviewed_at'
            ]);
        });
    }
}; 