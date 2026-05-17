<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $appends = [
        'permissions',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is staff.
     */
    public function isStaff(): bool
    {
        return in_array($this->role, ['staff', 'cashier'], true);
    }

    /**
     * Role-based access record.
     */
    public function rolePermission()
    {
        return $this->hasOne(RolePermission::class, 'role', 'role');
    }

    /**
     * Get permissions for the current role.
     */
    public function getPermissionsAttribute(): array
    {
        if ($this->isAdmin()) {
            return array_keys(config('role_permissions.permissions', []));
        }

        $permissions = $this->rolePermission?->permissions;

        if (is_array($permissions) && $permissions !== []) {
            return array_values(array_unique($permissions));
        }

        return array_values(config('role_permissions.defaults.' . $this->role, []));
    }

    /**
     * Check whether the role can access a permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->isAdmin() || in_array($permission, $this->permissions, true);
    }

    /**
     * Check whether the role can access at least one permission.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return (bool) array_intersect($permissions, $this->permissions);
    }
}
