<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Creates the first Super Administrator from ADMIN_EMAIL / ADMIN_PASSWORD in .env. */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD not set in .env; skipping super admin creation.');

            return;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'HGKA Administrator'),
                'password' => Hash::make($password),
                'is_active' => true,
                'must_change_password' => true,
            ],
        );

        $user->roles()->syncWithoutDetaching([Role::where('slug', 'super-admin')->value('id')]);
    }
}
