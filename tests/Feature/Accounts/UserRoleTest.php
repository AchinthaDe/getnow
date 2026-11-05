<?php

declare(strict_types=1);

use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use Database\Seeders\SuperadminSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;

/**
 * @internal
 */
interface MigrationWithRollback
{
    public function up(): void;

    public function down(): void;
}

it('has expected cases and backing values for user role enum', function (): void {
    $cases = UserRole::cases();
    $values = array_map(fn (UserRole $role) => $role->value, $cases);

    expect($cases)->toHaveCount(3)
        ->and($values)->toBe(['superadmin', 'retailer', 'customer']);
});

it('creates a customer user by default via factory with ulid public id', function (): void {
    $user = User::factory()->create();

    expect($user->public_id)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/')
        ->and($user->role)->toBe(UserRole::Customer);

    assertDatabaseHas('users', [
        'id' => $user->id,
        'public_id' => $user->public_id,
        'role' => 'customer',
    ]);
});

it('creates a superadmin user via factory state', function (): void {
    $user = User::factory()->superadmin()->create();

    expect($user->role)->toBe(UserRole::Superadmin);

    assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'superadmin',
    ]);
});

it('creates a retailer user via factory state', function (): void {
    $user = User::factory()->retailer()->create();

    expect($user->role)->toBe(UserRole::Retailer);

    assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'retailer',
    ]);
});

it('creates a customer user via factory state', function (): void {
    $user = User::factory()->customer()->create();

    expect($user->role)->toBe(UserRole::Customer);

    assertDatabaseHas('users', [
        'id' => $user->id,
        'role' => 'customer',
    ]);
});

it('hashes password transparently via model cast without hash facade', function (): void {
    $user = User::factory()->create(['password' => 'plain-text-secret']);

    expect(Hash::check('plain-text-secret', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('plain-text-secret');
});

it('enforces check constraint on role column in postgresql', function (): void {
    try {
        DB::table('users')->insert([
            'public_id' => (string) Str::ulid(),
            'name' => 'Invalid Role User',
            'email' => 'invalid@getnow.test',
            'password' => 'hashed-password',
            'role' => 'invalid_role',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(true)->toBeFalse('Expected QueryException was not thrown.');
    } catch (QueryException $e) {
        expect($e->getCode())->toBe('23514');
    }
});

it('seeds superadmin idempotently without duplicating records', function (): void {
    config([
        'auth.superadmin.email' => 'admin@getnow.test',
        'auth.superadmin.password' => 'secret123',
    ]);

    app(SuperadminSeeder::class)->run();
    app(SuperadminSeeder::class)->run();

    expect(User::where('email', 'admin@getnow.test')->count())->toBe(1);

    /** @var User $admin */
    $admin = User::where('email', 'admin@getnow.test')->first();
    expect($admin->role)->toBe(UserRole::Superadmin);
});

it('does not overwrite existing superadmin password on subsequent seed', function (): void {
    config([
        'auth.superadmin.email' => 'admin@getnow.test',
        'auth.superadmin.password' => 'secret123',
    ]);

    app(SuperadminSeeder::class)->run();

    /** @var User $user */
    $user = User::where('email', 'admin@getnow.test')->firstOrFail();
    $user->update(['password' => 'new-custom-password']);

    $recordedHash = $user->fresh()->password;

    app(SuperadminSeeder::class)->run();

    expect($user->fresh()->password)->toBe($recordedHash)
        ->and(Hash::check('new-custom-password', $user->fresh()->password))->toBeTrue();
});

it('supports soft deleting user instances', function (): void {
    $user = User::factory()->create();

    $user->delete();

    expect($user->trashed())->toBeTrue()
        ->and(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull();
});

it('successfully rolls back migration and removes added columns while restoring dropped columns', function (): void {
    /** @var Migration&MigrationWithRollback $migration */
    $migration = require database_path('migrations/2026_10_30_070000_add_accounts_columns_to_users_table.php');
    $migration->down();

    expect(Schema::hasColumn('users', 'public_id'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'role'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'deleted_at'))->toBeFalse()
        ->and(Schema::hasColumn('users', 'remember_token'))->toBeTrue()
        ->and(Schema::hasColumn('users', 'two_factor_secret'))->toBeTrue();

    $migration->up();
});
