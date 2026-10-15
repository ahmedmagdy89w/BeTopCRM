<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2eUserSeeder extends Seeder
{
    /**
     * Ensure the dedicated Playwright E2E admin exists (idempotent).
     *
     * Credentials default to e2e.admin@example.com / Password123! and can be
     * overridden with E2E_EMAIL / E2E_PASSWORD env vars.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $user = User::updateOrCreate(
            ['email' => env('E2E_EMAIL', 'e2e.admin@example.com')],
            [
                'name' => 'E2E Admin',
                'password' => Hash::make(env('E2E_PASSWORD', 'Password123!')),
                'is_active' => true,
            ]
        );

        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole('admin');
    }
}
