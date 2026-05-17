<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RolePermission;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function index()
    {
        $labels = config('role_permissions.labels', []);
        $available = config('role_permissions.permissions', []);
        $defaults = config('role_permissions.defaults', []);

        $roles = RolePermission::query()
            ->orderBy('role')
            ->get()
            ->map(function (RolePermission $record) use ($labels, $defaults) {
                return [
                    'role' => $record->role,
                    'label' => $record->label ?: ($labels[$record->role] ?? ucfirst(str_replace('_', ' ', $record->role))),
                    'permissions' => $record->permissions ?: ($defaults[$record->role] ?? []),
                ];
            })
            ->values();

        return response()->json([
            'roles' => $roles,
            'availablePermissions' => collect($available)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $availablePermissions = array_keys(config('role_permissions.permissions', []));

        $data = $request->validate([
            'role' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:role_permissions,role'],
            'label' => ['required', 'string', 'max:100'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', $availablePermissions)],
        ]);

        $record = RolePermission::query()->create([
            'role' => $data['role'],
            'label' => $data['label'],
            'permissions' => array_values(array_unique($data['permissions'])),
        ]);

        return response()->json([
            'role' => [
                'role' => $record->role,
                'label' => $record->label,
                'permissions' => $record->permissions,
            ],
        ], 201);
    }

    public function update(Request $request, string $role)
    {
        $record = RolePermission::query()->where('role', $role)->firstOrFail();
        $availablePermissions = array_keys(config('role_permissions.permissions', []));

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:100'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'in:' . implode(',', $availablePermissions)],
        ]);

        if (array_key_exists('label', $data)) {
            $record->label = $data['label'];
        }

        if (array_key_exists('permissions', $data)) {
            $record->permissions = array_values(array_unique($data['permissions']));
        }

        $record->save();

        return response()->json([
            'role' => [
                'role' => $record->role,
                'label' => $record->label ?: config('role_permissions.labels.' . $role, ucfirst(str_replace('_', ' ', $role))),
                'permissions' => $record->permissions,
            ],
        ]);
    }

    public function destroy(string $role)
    {
        abort_if(in_array($role, array_keys(config('role_permissions.defaults', [])), true), 403, 'Built-in roles cannot be deleted.');

        $record = RolePermission::query()->where('role', $role)->firstOrFail();
        $record->delete();

        return response()->json(['message' => 'Role deleted']);
    }
}
