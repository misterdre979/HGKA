<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\GradingScaleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(GradingScaleSeeder::class);
    }

    /** Creates an active user holding the given role slug. */
    protected function userWithRole(string $slug, array $attrs = []): User
    {
        $user = User::create(array_merge([
            'name' => ucfirst($slug).' User', 'email' => $slug.'+'.uniqid().'@test.local',
            'password' => 'Password#123', 'is_active' => true, 'must_change_password' => false,
        ], $attrs));
        $user->roles()->sync([Role::where('slug', $slug)->value('id')]);

        return $user;
    }

    /** Bearer-token auth header for a user (mirrors the real login flow). */
    protected function tokenFor(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken, 'Accept' => 'application/json'];
    }
}
