<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_returns_token_and_permissions(): void
    {
        $user = $this->userWithRole('teacher', ['email' => 'teach@test.local']);

        $res = $this->postJson('/api/v1/auth/login', ['email' => 'teach@test.local', 'password' => 'Password#123']);

        $res->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'roles', 'permissions']]);
        $this->assertContains('attendance.take', $res->json('user.permissions'));
    }

    public function test_wrong_password_is_rejected_without_revealing_which_part_failed(): void
    {
        $this->userWithRole('teacher', ['email' => 'teach@test.local']);

        $this->postJson('/api/v1/auth/login', ['email' => 'teach@test.local', 'password' => 'nope'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['email' => 'ghost@test.local', 'password' => 'nope'])->assertStatus(422);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->userWithRole('teacher', ['email' => 'off@test.local', 'is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => 'off@test.local', 'password' => 'Password#123'])->assertStatus(422);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $last = $this->postJson('/api/v1/auth/login', ['email' => 'x@test.local', 'password' => 'bad']);
        }
        $last->assertStatus(429);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_must_change_password_blocks_the_api_until_changed(): void
    {
        $user = $this->userWithRole('teacher', ['must_change_password' => true]);
        $h = $this->tokenFor($user);

        $this->getJson('/api/v1/dashboard', $h)->assertStatus(423);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Password#123', 'password' => 'NewPassw0rd!x', 'password_confirmation' => 'NewPassw0rd!x',
        ], $h)->assertOk();

        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_weak_new_password_is_rejected(): void
    {
        $user = $this->userWithRole('teacher');

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Password#123', 'password' => 'short', 'password_confirmation' => 'short',
        ], $this->tokenFor($user))->assertStatus(422);
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->userWithRole('teacher');
        $h = $this->tokenFor($user);

        $this->postJson('/api/v1/auth/logout', [], $h)->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }
}
