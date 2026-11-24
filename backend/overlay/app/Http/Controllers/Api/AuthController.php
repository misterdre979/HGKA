<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public static function payload(User $user): array
    {
        $user->loadMissing(['roles', 'staff:id,user_id', 'student:id,user_id', 'guardian:id,user_id']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
            'must_change_password' => (bool) $user->must_change_password,
            'roles' => $user->roleSlugs(),
            'role_names' => $user->roles->pluck('name')->all(),
            'permissions' => $user->permissionNames(),
            'staff_id' => $user->staff?->id,
            'student_id' => $user->student?->id,
            'guardian_id' => $user->guardian?->id,
        ];
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            Audit::log('login.failed', null, null, ['email' => $data['email']]);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        if (! $user->is_active) {
            Audit::log('login.blocked', $user, null, null, $user->id);
            throw ValidationException::withMessages(['email' => 'This account has been disabled. Contact the school administrator.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('web', ['*'], now()->addHours(config('hgka.token_hours')))->plainTextToken;
        Audit::log('login', $user, null, null, $user->id);

        return response()->json(['token' => $token, 'user' => self::payload($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Audit::log('logout');
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => self::payload($request->user())]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers(), 'different:current_password'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $current = $user->currentAccessToken();
        $others = $user->tokens();
        if ($current instanceof PersonalAccessToken) {
            $others->where('id', '!=', $current->id);
        }
        $others->delete();
        Audit::log('password.changed', $user);

        return response()->json(['message' => 'Password updated.', 'user' => self::payload($user->fresh())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }
        unset($data['avatar']);
        $user->update($data);

        return response()->json(['user' => self::payload($user->fresh())]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        // Same response whether or not the account exists (prevents account enumeration).
        return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'must_change_password' => false, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
            Audit::log('password.reset', $user, null, null, $user->id);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or has expired.']);
        }

        return response()->json(['message' => 'Password reset. You can now sign in.']);
    }
}
