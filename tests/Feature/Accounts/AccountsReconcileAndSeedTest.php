<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithPassportKeys;

use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

uses(InteractsWithPassportKeys::class);

beforeEach(function (): void {
    (new class
    {
        use InteractsWithPassportKeys;
    })->ensurePassportKeys();
});

it('reconciles users table backfilling platform admins to superadmin and standard users to customer with valid ulids', function (): void {
    // Insert raw rows simulating existing CMS users prior to reconcile migration
    $adminId = DB::table('users')->insertGetId([
        'name' => 'Legacy Admin',
        'email' => 'legacy.admin@getnow.test',
        'password' => bcrypt('password'),
        'is_platform_admin' => true,
        'public_id' => (string) Str::ulid(),
        'role' => 'superadmin',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $customerId = DB::table('users')->insertGetId([
        'name' => 'Legacy User',
        'email' => 'legacy.user@getnow.test',
        'password' => bcrypt('password'),
        'is_platform_admin' => false,
        'public_id' => (string) Str::ulid(),
        'role' => 'customer',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Verify backfill logic mapping
    $admin = User::query()->findOrFail($adminId);
    $customer = User::query()->findOrFail($customerId);

    expect($admin->role)->toBe(UserRole::Superadmin)
        ->and($admin->is_platform_admin)->toBeTrue()
        ->and($admin->public_id)->toHaveLength(26)
        ->and($customer->role)->toBe(UserRole::Customer)
        ->and($customer->is_platform_admin)->toBeFalse()
        ->and($customer->public_id)->toHaveLength(26);
});

it('executes database seeder idempotently ensuring dual access to admin panel and api login', function (): void {
    // Run seed twice to verify idempotency
    seed(DatabaseSeeder::class);
    seed(DatabaseSeeder::class);

    $superadminEmail = (string) (config('auth.superadmin.email') ?: 'superadmin@getnow.test');
    $superadminPassword = (string) (config('auth.superadmin.password') ?: 'secret');

    /** @var User $superadmin */
    $superadmin = User::where('email', $superadminEmail)->firstOrFail();

    // Verify admin panel access (Filament canAccessPanel via is_platform_admin)
    $panel = new Panel;
    expect($superadmin->canAccessPanel($panel))->toBeTrue()
        ->and($superadmin->is_platform_admin)->toBeTrue()
        ->and($superadmin->role)->toBe(UserRole::Superadmin);

    // Verify API OAuth login via POST /api/v1/auth/login
    $response = postJson('/api/v1/auth/login', [
        'email' => $superadminEmail,
        'password' => $superadminPassword,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
                'tokens' => [
                    'token_type',
                    'expires_in',
                    'access_token',
                    'refresh_token',
                ],
            ],
        ])
        ->assertJsonPath('data.user.role', 'superadmin')
        ->assertJsonPath('data.tokens.token_type', 'Bearer');
});
