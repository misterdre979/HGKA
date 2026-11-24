<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,name')->withCount('users')->orderBy('id')->get()
            ->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'slug' => $r->slug, 'description' => $r->description,
                'is_system' => $r->is_system, 'users_count' => $r->users_count,
                'permissions' => $r->permissions->pluck('name')->all(),
            ]);

        return response()->json($roles);
    }

    /** All permissions grouped by module, for the role editor. */
    public function permissions(): JsonResponse
    {
        return response()->json(
            Permission::orderBy('module')->orderBy('name')->get(['id', 'name', 'module'])->groupBy('module')
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $role = Role::create([
            'name' => $data['name'], 'slug' => Str::slug($data['name']), 'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::whereIn('name', $data['permissions'] ?? [])->pluck('id'));
        Audit::log('roles.created', $role, null, $data);

        return response()->json($role, 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_if($role->slug === 'super-admin', 422, 'The super administrator role cannot be edited.');

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        if (isset($data['name']) && $role->is_system) {
            unset($data['name']); // system role names are fixed
        }
        $old = $role->permissions()->pluck('name')->all();
        if (isset($data['permissions'])) {
            $role->permissions()->sync(Permission::whereIn('name', $data['permissions'])->pluck('id'));
        }
        $role->update(array_intersect_key($data, array_flip(['name', 'description'])));
        Audit::log('roles.updated', $role, ['permissions' => $old], ['permissions' => $data['permissions'] ?? $old]);

        return response()->json($role->fresh());
    }

    public function destroy(Role $role): JsonResponse
    {
        abort_if($role->is_system, 422, 'System roles cannot be deleted.');
        abort_if($role->users()->exists(), 422, 'Remove this role from all users before deleting it.');
        $role->delete();
        Audit::log('roles.deleted', $role);

        return response()->json(['message' => 'Role deleted.']);
    }
}
