<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\UserPortalAssignment;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    // Ensure Spatie permissions use the correct guard
    protected string $guard_name = 'web';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'kyc_status',
        'kyc_data',
        'zoho_user_id',
        'is_active',
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
        'preferences',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'date_of_birth' => 'date',
        'kyc_data' => 'array',
        'preferences' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Relation: per-user portal assignments with flags.
     */
    public function portalAssignments()
    {
        return $this->hasMany(UserPortalAssignment::class);
    }

    /**
     * Accessor to return a normalized array of portal assignments
     * [
     *   ['portal' => 'analytics', 'can_view' => true, 'can_manage' => false],
     *   ...
     * ]
     */
    public function getPortalAssignmentsArrayAttribute(): array
    {
        // If the table doesn't exist yet (e.g., fresh env without this migration), avoid crashing
        if (!Schema::hasTable('user_portal_assignments')) {
            return [];
        }

        try {
            $assignments = $this->relationLoaded('portalAssignments')
                ? $this->getRelation('portalAssignments')
                : $this->portalAssignments()->get();

            return collect($assignments)
                ->map(function ($a) {
                    return [
                        'portal' => $a->portal,
                        'can_view' => (bool) $a->can_view,
                        'can_manage' => (bool) $a->can_manage,
                    ];
                })
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            // Fail safe: do not block authentication payloads due to assignment issues
            return [];
        }
    }
}
