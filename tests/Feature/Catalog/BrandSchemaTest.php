<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('creates brands table with expected columns and defaults', function (): void {
    expect(Schema::hasTable('brands'))->toBeTrue();

    $columns = Schema::getColumnListing('brands');
    expect($columns)->toContain('id', 'public_id', 'name', 'slug', 'is_active', 'show_in_nav', 'created_by', 'updated_by', 'created_at', 'updated_at');

    $type = Schema::getColumnType('brands', 'public_id');
    expect($type)->toBeIn(['string', 'char', 'bpchar']);

    DB::table('brands')->insert([
        'public_id' => (string) Str::ulid(),
        'name' => 'Default Test Brand',
        'slug' => 'default-test-brand',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $inserted = DB::table('brands')->where('slug', 'default-test-brand')->first();
    expect($inserted)->not->toBeNull()
        ->and((bool) $inserted->is_active)->toBeTrue()
        ->and((bool) $inserted->show_in_nav)->toBeFalse();
});

it('rejects duplicate public_id at database level', function (): void {
    $ulid = (string) Str::ulid();

    DB::table('brands')->insert([
        'public_id' => $ulid,
        'name' => 'Brand One',
        'slug' => 'brand-one',
        'is_active' => true,
        'show_in_nav' => false,
        'created_by' => null,
        'updated_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('brands')->insert([
        'public_id' => $ulid,
        'name' => 'Brand Two',
        'slug' => 'brand-two',
        'is_active' => true,
        'show_in_nav' => false,
        'created_by' => null,
        'updated_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects duplicate slug at database level', function (): void {
    DB::table('brands')->insert([
        'public_id' => (string) Str::ulid(),
        'name' => 'Brand Alpha',
        'slug' => 'brand-slug',
        'is_active' => true,
        'show_in_nav' => false,
        'created_by' => null,
        'updated_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('brands')->insert([
        'public_id' => (string) Str::ulid(),
        'name' => 'Brand Beta',
        'slug' => 'brand-slug',
        'is_active' => true,
        'show_in_nav' => false,
        'created_by' => null,
        'updated_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects case-variant duplicate name via functional unique index', function (): void {
    DB::table('brands')->insert([
        'public_id' => (string) Str::ulid(),
        'name' => 'Samsung',
        'slug' => 'samsung',
        'is_active' => true,
        'show_in_nav' => false,
        'created_by' => null,
        'updated_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('brands')->insert([
        'public_id' => (string) Str::ulid(),
        'name' => 'samsung',
        'slug' => 'samsung-variant',
        'is_active' => true,
        'show_in_nav' => false,
        'created_by' => null,
        'updated_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects malformed slug via check constraint', function (): void {
    expect(fn () => DB::table('brands')->insert([
        'public_id' => (string) Str::ulid(),
        'name' => 'Invalid Slug Brand',
        'slug' => 'Invalid_Slug!',
        'is_active' => true,
        'show_in_nav' => false,
        'created_by' => null,
        'updated_by' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rolls back migration cleanly', function (): void {
    // In PostgreSQL, DDL commands (such as DROP TABLE or ALTER TABLE in migrations) commit the
    // open transaction immediately, bypassing RefreshDatabase's transaction isolation.
    // We execute rollback and re-migrate inside a try / finally block to ensure the brands table
    // is restored for any subsequent tests.
    expect(Schema::hasTable('brands'))->toBeTrue();

    try {
        while (Schema::hasTable('brands')) {
            Artisan::call('migrate:rollback', ['--step' => 1, '--no-interaction' => true]);
        }
        expect(Schema::hasTable('brands'))->toBeFalse();
    } finally {
        Artisan::call('migrate', ['--no-interaction' => true]);
    }
});
