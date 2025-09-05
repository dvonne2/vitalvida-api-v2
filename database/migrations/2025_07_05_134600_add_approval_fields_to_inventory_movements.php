<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'rejected', 'auto_approved'])
                  ->default('pending')
                  ->after('notes');
            
            $table->unsignedBigInteger('approved_by')->nullable()->after('approval_status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_notes')->nullable()->after('approved_at');
            $table->decimal('approval_threshold', 10, 2)->default(1000.00)->after('approval_notes');
            
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['approval_status', 'created_at']);
            $table->index(['approved_by', 'approved_at']);
        });
    }

    public function down()
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['approval_status', 'created_at']);
            $table->dropIndex(['approved_by', 'approved_at']);
            $table->dropColumn([
                'approval_status',
                'approved_by', 
                'approved_at',
                'approval_notes',
                'approval_threshold'
            ]);
        });
    }
};
