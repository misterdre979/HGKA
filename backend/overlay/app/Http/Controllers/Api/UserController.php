<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public static function temporaryPassword(): string
    {
        return Str::password(10, true, true, false).'Aa1';
    }

    public function index(Request $request): JsonResponse
    {
        $q = User::query()->with('roles:id,name,slug');

        if (($s = trim((string) $request->query('search', ''))) !== '') {
            $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"));
        }
        if ($role = $request->query('role')) {
            $q->whereHas('roles', fn ($r) => $r->where('slug', $role));
        }
        if (($active = $request->query('active')) !== null && $active !== '') {
            $q->where('is_active', filter_var($active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($q->orderBy('name')->paginate(min((int) $request->query('per_page', 15), 100)));
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('roles:id,name,slug'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'slug')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $this->guardSuperAdminRole($request, $data['roles']);

        $temp = self::temporaryPassword();
        $user = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'password' => $temp, 'is_active' => $data['is_active'] ?? true, 'must_change_password' => true,
        ]);
        $user->roles()->sync(Role::whereIn('slug', $data['roles'])->pluck('id'));
        Audit::log('users.created', $user, null, ['email' => $user->email, 'roles' => $data['roles']]);

        return response()->json(['user' => $user->load('roles:id,name,slug'), 'temporary_password' => $temp], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->guardSuperAdminUser($request, $user);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'slug')],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('is_active', $data) && ! $data['is_active'] && $user->id === $request->user()->id) {
            throw ValidationException::withMessages(['is_active' => 'You cannot deactivate your own account.']);
        }
        if (isset($data['roles'])) {
            $this->guardSuperAdminRole($request, $data['roles']);
            if ($user->id === $request->user()->id && $user->hasRole('super-admin') && ! in_array('super-admin', $data['roles'], true)) {
                throw ValidationException::withMessages(['roles' => 'You cannot remove your own super administrator role.']);
            }
            $user->roles()->sync(Role::whereIn('slug', $data['roles'])->pluck('id'));
        }

        unset($data['roles']);
        $user->update($data);
        if (isset($data['is_active']) && ! $data['is_active']) {
            $user->tokens()->delete();
        }
        Audit::log('users.updated', $user, null, $data);

        return response()->json($user->fresh('roles:id,name,slug'));
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->guardSuperAdminUser($request, $user);
        $temp = self::temporaryPassword();
        $user->forceFill(['password' => $temp, 'must_change_password' => true])->save();
        $user->tokens()->delete();
        Audit::log('users.password_reset', $user);

        return response()->json(['temporary_password' => $temp]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->guardSuperAdminUser($request, $user);
        abort_if($user->id === $request->user()->id, 422, 'You cannot delete your own account.');
        $user->tokens()->delete();
        $user->delete();
        Audit::log('users.deleted', $user);

        return response()->json(['message' => 'User removed.']);
    }

    private function guardSuperAdminRole(Request $request, array $slugs): void
    {
        if (in_array('super-admin', $slugs, true) && ! $request->user()->hasRole('super-admin')) {
            abort(403, 'Only a super administrator can assign the super administrator role.');
        }
    }

    private function guardSuperAdminUser(Request $request, User $target): void
    {
        if ($target->hasRole('super-admin') && ! $request->user()->hasRole('super-admin')) {
            abort(403, 'Only a super administrator can modify a super administrator.');
        }
    }
}
