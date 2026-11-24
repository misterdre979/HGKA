<?php

namespace App\Models\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    private ?array $permissionCache = null;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function roleSlugs(): array
    {
        return $this->roles->pluck('slug')->all();
    }

    public function hasRole(string ...$slugs): bool
    {
        return count(array_intersect($this->roleSlugs(), $slugs)) > 0;
    }

    /** Names of every permission the user holds (super administrators hold all). */
    public function permissionNames(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        if ($this->hasRole('super-admin')) {
            return $this->permissionCache = Permission::orderBy('name')->pluck('name')->all();
        }

        return $this->permissionCache = Permission::whereHas('roles.users', fn ($q) => $q->where('users.id', $this->id))
            ->orderBy('name')->pluck('name')->unique()->values()->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionNames(), true);
    }

    /** True when the user holds at least one of the given permissions. */
    public function hasAnyPermission(array $permissions): bool
    {
        return count(array_intersect($this->permissionNames(), $permissions)) > 0;
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }
}
