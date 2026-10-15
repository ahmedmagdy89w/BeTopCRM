<?php

use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        CompanySetting::forgetCache();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Shared test helpers
|--------------------------------------------------------------------------
*/

function adminUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('admin');

    return $user;
}

function managerUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('manager');

    return $user;
}

function salesRepUser(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('sales_rep');

    return $user;
}
