<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->unsignedBigInteger('locked_by')->nullable()->after('approval_notes');
            $table->timestamp('locked_at')->nullable()->after('locked_by');
            $table->text('lock_reason')->nullable()->after('locked_at');
            $table->enum('lock_type', ['fraud', 'dispute', 'investigation', 'compliance'])->nullable()->after('lock_reason');
            
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('locked_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['locked_by']);
            $table->dropColumn([
                'approved_by', 'approved_at', 'approval_notes',
                'locked_by', 'locked_at', 'lock_reason', 'lock_type'
            ]);
        });
    }
};
