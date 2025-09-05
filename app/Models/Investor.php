<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Investor extends Authenticatable
{
    use HasFactory, SoftDeletes, HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'access_level',
        'permissions',
        'preferences',
        'company_name',
        'position',
        'bio',
        'profile_image',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'email_verified_at',
        'phone_verified_at',
        'password'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'permissions' => 'array',
        'preferences' => 'array',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Role constants
    const ROLE_MASTER_READINESS = 'master_readiness';
    const ROLE_TOMI_GOVERNANCE = 'tomi_governance';
    const ROLE_RON_SCALE = 'ron_scale';
    const ROLE_THIEL_STRATEGY = 'thiel_strategy';
    const ROLE_ANDY_TECH = 'andy_tech';
    const ROLE_OTUNBA_CONTROL = 'otunba_control';
    const ROLE_DANGOTE_COST_CONTROL = 'dangote_cost_control';
    const ROLE_NEIL_GROWTH = 'neil_growth';

    // Access level constants
    const ACCESS_FULL = 'full';
    const ACCESS_LIMITED = 'limited';
    const ACCESS_READONLY = 'readonly';

    // Relationships
    public function sessions()
    {
        return $this->hasMany(InvestorSession::class);
    }

    public function documents()
    {
        return $this->belongsToMany(InvestorDocument::class, 'investor_document_access')
            ->withPivot('can_view', 'can_download', 'can_edit')
            ->withTimestamps();
    }

    public function financialStatements()
    {
        return $this->hasMany(FinancialStatement::class, 'prepared_by');
    }

    public function companyValuations()
    {
        return $this->hasMany(CompanyValuation::class, 'prepared_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByAccessLevel($query, $accessLevel)
    {
        return $query->where('access_level', $accessLevel);
    }

    // Business Logic Methods
    public function hasPermission($permission)
    {
        if ($this->access_level === self::ACCESS_FULL) {
            return true;
        }

        return in_array($permission, $this->permissions ?? []);
    }

    public function canAccessDocument($document)
    {
        // Check if investor has access to this document based on role
        $requiredRoles = $document->access_permissions ?? [];
        
        if (empty($requiredRoles)) {
            return true; // No restrictions
        }

        return in_array($this->role, $requiredRoles);
    }

    public function getRoleDisplayName()
    {
        $roleNames = [
            self::ROLE_MASTER_READINESS => 'Master Readiness',
            self::ROLE_TOMI_GOVERNANCE => 'Tomi Governance',
            self::ROLE_RON_SCALE => 'Ron Scale',
            self::ROLE_THIEL_STRATEGY => 'Thiel Strategy',
            self::ROLE_ANDY_TECH => 'Andy Tech',
            self::ROLE_OTUNBA_CONTROL => 'Otunba Control',
            self::ROLE_DANGOTE_COST_CONTROL => 'Dangote Cost Control',
            self::ROLE_NEIL_GROWTH => 'Neil Growth'
        ];

        return $roleNames[$this->role] ?? $this->role;
    }

    public function getAccessLevelDisplayName()
    {
        $accessNames = [
            self::ACCESS_FULL => 'Full Access',
            self::ACCESS_LIMITED => 'Limited Access',
            self::ACCESS_READONLY => 'Read Only'
        ];

        return $accessNames[$this->access_level] ?? $this->access_level;
    }

    public function updateLastLogin($ipAddress = null)
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress
        ]);
    }

    public function getDashboardPreferences()
    {
        return $this->preferences['dashboard'] ?? [
            'default_view' => 'overview',
            'refresh_interval' => 300, // 5 minutes
            'show_alerts' => true,
            'show_metrics' => true,
            'show_documents' => true
        ];
    }

    public function getNotificationPreferences()
    {
        return $this->preferences['notifications'] ?? [
            'email_notifications' => true,
            'document_updates' => true,
            'financial_reports' => true,
            'valuation_updates' => true
        ];
    }

    // Role-specific access methods
    public function canAccessFinancials()
    {
        return in_array($this->role, [
            self::ROLE_MASTER_READINESS,
            self::ROLE_TOMI_GOVERNANCE,
            self::ROLE_OTUNBA_CONTROL,
            self::ROLE_DANGOTE_COST_CONTROL
        ]);
    }

    public function canAccessOperations()
    {
        return in_array($this->role, [
            self::ROLE_MASTER_READINESS,
            self::ROLE_RON_SCALE,
            self::ROLE_ANDY_TECH
        ]);
    }

    public function canAccessGovernance()
    {
        return in_array($this->role, [
            self::ROLE_MASTER_READINESS,
            self::ROLE_TOMI_GOVERNANCE
        ]);
    }

    public function canAccessStrategy()
    {
        return in_array($this->role, [
            self::ROLE_MASTER_READINESS,
            self::ROLE_THIEL_STRATEGY,
            self::ROLE_NEIL_GROWTH
        ]);
    }

    public function canAccessTechMetrics()
    {
        return in_array($this->role, [
            self::ROLE_MASTER_READINESS,
            self::ROLE_ANDY_TECH
        ]);
    }

    public function canAccessGrowthMetrics()
    {
        return in_array($this->role, [
            self::ROLE_MASTER_READINESS,
            self::ROLE_NEIL_GROWTH,
            self::ROLE_RON_SCALE
        ]);
    }
}
