<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;

class ComprehensiveAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        // Clean up legacy role.* portal permissions (deprecated)
        $legacyPortalPerms = [
            'role.analytics.view',
            'role.financial_controller.view',
            'role.auditor.view',
        ];
        $legacy = Permission::whereIn('name', $legacyPortalPerms)->where('guard_name', 'web')->get();
        foreach ($legacy as $perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
        // ensure cache is cleared after deletions
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Core system permissions
        $systemPermissions = [
            // Dashboard & Analytics
            'dashboard.view',
            'dashboard.analytics.view',
            'dashboard.reports.view',

            // User Management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.impersonate',

            // Role & Permission Management
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',

            // System Settings
            'settings.view',
            'settings.edit',
            'settings.system.edit',
            'settings.email.edit',
            'settings.notifications.edit',

            // Audit & Logs
            'logs.view',
            'audit.view',
            'system.maintenance',

            // API Management
            'api.tokens.view',
            'api.tokens.create',
            'api.tokens.delete',
        ];

        // Portal permissions
        $portalPermissions = [
            // Sales Portal
            'portal.sales.view',
            'portal.sales.create',
            'portal.sales.edit',
            'portal.sales.delete',
            'portal.sales.reports',
            'portal.sales.admin',

            // Inventory Portal
            'portal.inventory.view',
            'portal.inventory.create',
            'portal.inventory.edit',
            'portal.inventory.delete',
            'portal.inventory.reports',
            'portal.inventory.admin',

            // Finance Portal
            'portal.finance.view',
            'portal.finance.create',
            'portal.finance.edit',
            'portal.finance.delete',
            'portal.finance.reports',
            'portal.finance.admin',

            // HR Portal
            'portal.hr.view',
            'portal.hr.create',
            'portal.hr.edit',
            'portal.hr.delete',
            'portal.hr.reports',
            'portal.hr.admin',

            // Customers Portal
            'portal.customers.view',
            'portal.customers.create',
            'portal.customers.edit',
            'portal.customers.delete',
            'portal.customers.reports',

            // Operations Portal
            'portal.operations.view',
            'portal.operations.create',
            'portal.operations.edit',
            'portal.operations.delete',
            'portal.operations.reports',
        ];

        $allPermissions = array_values(array_unique(array_merge($systemPermissions, $portalPermissions)));

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Important: clear Spatie cached permissions before role assignment
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'superadmin' => $allPermissions,
            'system-admin' => array_merge($systemPermissions, [
                'portal.sales.view',
                'portal.inventory.view',
                'portal.finance.view',
                'portal.hr.view',
            ]),
            'sales-manager' => [
                'dashboard.view',
                'portal.sales.view',
                'portal.sales.create',
                'portal.sales.edit',
                'portal.sales.delete',
                'portal.sales.reports',
                'portal.sales.admin',
                'users.view',
            ],
            'inventory-manager' => [
                'dashboard.view',
                'portal.inventory.view',
                'portal.inventory.create',
                'portal.inventory.edit',
                'portal.inventory.delete',
                'portal.inventory.reports',
                'users.view',
            ],
            'finance-manager' => [
                'dashboard.view',
                'portal.finance.view',
                'portal.finance.create',
                'portal.finance.edit',
                'portal.finance.delete',
                'portal.finance.reports',
                'users.view',
            ],
            'hr-manager' => [
                'dashboard.view',
                'portal.hr.view',
                'portal.hr.create',
                'portal.hr.edit',
                'portal.hr.delete',
                'portal.hr.reports',
                'users.view',
                'users.create',
                'users.edit',
            ],
            'sales-rep' => [
                'dashboard.view',
                'portal.sales.view',
                'portal.sales.create',
                'portal.sales.edit',
            ],
            'inventory-clerk' => [
                'dashboard.view',
                'portal.inventory.view',
                'portal.inventory.create',
                'portal.inventory.edit',
            ],
            'accountant' => [
                'dashboard.view',
                'portal.finance.view',
                'portal.finance.create',
                'portal.finance.edit',
                'portal.finance.reports',
            ],
            'hr-assistant' => [
                'dashboard.view',
                'portal.hr.view',
                'portal.hr.create',
                'portal.hr.edit',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            // Resolve permission IDs by name to avoid relation null issues
            $permIds = Permission::whereIn('name', $rolePermissions)
                ->where('guard_name', 'web')
                ->pluck('id')
                ->toArray();

            $role->permissions()->sync($permIds);
        }

        // Ensure known admin emails have superadmin
        $adminEmails = [
            'admin@vitalvida.com',
            'admin@vitalvida.ng',
            'super@admin.com',
        ];

        foreach ($adminEmails as $email) {
            $adminUser = User::where('email', $email)->first();
            if ($adminUser) {
                $adminUser->assignRole('superadmin');
            }
        }
    }
}
