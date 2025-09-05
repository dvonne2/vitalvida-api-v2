<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    // Business Logic Methods
    public function getCategoryLabelAttribute(): string
    {
        return match($this->category) {
            'inventory' => 'Inventory Management',
            'purchase_orders' => 'Purchase Orders',
            'sales' => 'Sales Management',
            'reports' => 'Reports & Analytics',
            'users' => 'User Management',
            'system' => 'System Administration',
            'finance' => 'Financial Management',
            'delivery' => 'Delivery Management',
            default => ucfirst(str_replace('_', ' ', $this->category))
        };
    }

    public function getActionLabelAttribute(): string
    {
        $parts = explode('.', $this->slug);
        $action = end($parts);
        
        return match($action) {
            'view' => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'approve' => 'Approve',
            'receive' => 'Receive',
            'process' => 'Process',
            'refund' => 'Refund',
            'export' => 'Export',
            'financial' => 'Financial',
            'settings' => 'Settings',
            'backup' => 'Backup',
            'logs' => 'Logs',
            default => ucfirst($action)
        };
    }

    public function getFullNameAttribute(): string
    {
        return $this->category_label . ' - ' . $this->action_label;
    }

    public function getRolesCountAttribute(): int
    {
        return $this->roles()->count();
    }

    public function isSystemPermission(): bool
    {
        return in_array($this->slug, [
            'system.settings',
            'system.backup',
            'system.logs',
            'users.view',
            'users.create',
            'users.update',
            'users.delete'
        ]);
    }

    public function canBeDeleted(): bool
    {
        return !$this->isSystemPermission() && $this->roles_count === 0;
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($permission) {
            if (empty($permission->slug)) {
                $permission->slug = strtolower(str_replace(' ', '_', $permission->name));
            }
        });
    }
} 