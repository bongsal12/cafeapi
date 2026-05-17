<?php

namespace Database\Seeders;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (config('role_permissions.defaults', []) as $role => $permissions) {
            RolePermission::updateOrCreate(
                ['role' => $role],
                [
                    'label' => config('role_permissions.labels.' . $role, ucfirst(str_replace('_', ' ', $role))),
                    'permissions' => $permissions,
                ]
            );
        }

        // Keep demo users up to date on repeated seeding.
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'admin',
                'password' => Hash::make('12345678'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'staff',
                'password' => Hash::make('12345678'),
                'role' => 'staff',
            ]
        );

        User::updateOrCreate(
            ['email' => 'inventory@example.com'],
            [
                'name' => 'inventory',
                'password' => Hash::make('12345678'),
                'role' => 'inventory_staff',
            ]
        );
    }
}
