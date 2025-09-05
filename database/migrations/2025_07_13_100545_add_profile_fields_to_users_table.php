<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('password');
            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable()->after('date_of_birth');
            $table->text('address')->nullable()->after('gender');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('country', 100)->nullable()->after('state');
            $table->string('postal_code', 20)->nullable()->after('country');
            $table->string('emergency_contact')->nullable()->after('postal_code');
            $table->string('emergency_phone', 20)->nullable()->after('emergency_contact');
            $table->text('bio')->nullable()->after('emergency_phone');
            $table->json('preferences')->nullable()->after('bio');
            
            // Add indexes for commonly searched fields
            $table->index(['city', 'state']);
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['city', 'state']);
            $table->dropIndex(['country']);
            
            $table->dropColumn([
                'date_of_birth',
                'gender', 
                'address',
                'city',
                'state',
                'country',
                'postal_code',
                'emergency_contact',
                'emergency_phone',
                'bio',
                'preferences'
            ]);
        });
    }
};
