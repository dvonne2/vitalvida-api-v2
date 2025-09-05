<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Full system access'
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Management level access'
            ],
            [
                'name' => 'user',
                'display_name' => 'User',
                'description' => 'Basic user access'
            ],
            [
                'name' => 'superadmin',
                'display_name' => 'Super Administrator',
                'description' => 'Super admin access'
            ],
            [
                'name' => 'production',
                'display_name' => 'Production Manager',
                'description' => 'Production management access'
            ],
            [
                'name' => 'inventory',
                'display_name' => 'Inventory Manager',
                'description' => 'Inventory management access'
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}
