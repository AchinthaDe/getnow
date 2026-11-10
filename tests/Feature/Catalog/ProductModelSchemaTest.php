<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('creates product_models table with expected columns and defaults', function (): void {
    expect(Schema::hasTable('product_models'))->toBeTrue();

    $columns = Schema::getColumnListing('product_models');
    expect($columns)->toContain('id', 'public_id', 'brand_id', 'category_id', 'name', 'slug', 'is_active', 'created_by', 'updated_by', 'created_at', 'updated_at');

    $type = Schema::getColumnType('product_models', 'public_id');
    expect($type)->toBeIn(['string', 'char', 'bpchar']);

    $brandId = DB::table('brands')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Default Model Brand',
        'slug' => 'default-model-brand',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $categoryId = DB::table('categories')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Default Model Category',
        'slug' => 'default-model-category',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('product_models')->insert([
        'public_id' => (string) Str::ulid(),
        'brand_id' => $brandId,
        'category_id' => $categoryId,
        'name' => 'Default Test Model',
        'slug' => 'default-test-model',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $inserted = DB::table('product_models')->where('slug', 'default-test-model')->first();
    expect($inserted)->not->toBeNull()
        ->and((bool) $inserted->is_active)->toBeTrue();
});

it('rejects duplicate public_id at database level', function (): void {
    $brandId = DB::table('brands')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Brand Public ID Test',
        'slug' => 'brand-public-id-test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $categoryId = DB::table('categories')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Category Public ID Test',
        'slug' => 'category-public-id-test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $ulid = (string) Str::ulid();

    DB::table('product_models')->insert([
        'public_id' => $ulid,
        'brand_id' => $brandId,
        'category_id' => $categoryId,
        'name' => 'Model One',
        'slug' => 'model-one',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('product_models')->insert([
        'public_id' => $ulid,
        'brand_id' => $brandId,
        'category_id' => $categoryId,
        'name' => 'Model Two',
        'slug' => 'model-two',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects duplicate slug at database level', function (): void {
    $brandId = DB::table('brands')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Brand Slug Test',
        'slug' => 'brand-slug-test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $categoryId = DB::table('categories')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Category Slug Test',
        'slug' => 'category-slug-test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('product_models')->insert([
        'public_id' => (string) Str::ulid(),
        'brand_id' => $brandId,
        'category_id' => $categoryId,
        'name' => 'iPhone 15',
        'slug' => 'iphone-15',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('product_models')->insert([
        'public_id' => (string) Str::ulid(),
        'brand_id' => $brandId,
        'category_id' => $categoryId,
        'name' => 'iPhone 15 Duplicate Slug',
        'slug' => 'iphone-15',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects case-variant duplicate name within same brand via functional unique index', function (): void {
    $brandId = DB::table('brands')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Apple',
        'slug' => 'apple',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $categoryId = DB::table('categories')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Smartphones',
        'slug' => 'smartphones',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('product_models')->insert([
        'public_id' => (string) Str::ulid(),
        'brand_id' => $brandId,
        'category_id' => $categoryId,
        'name' => 'iPhone 15',
        'slug' => 'iphone-15',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('product_models')->insert([
        'public_id' => (string) Str::ulid(),
        'brand_id' => $brandId,
        'category_id' => $categoryId,
        'name' => 'iphone 15',
        'slug' => 'iphone-15-lowercase',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows same name under different brands', function (): void {
    $brand1Id = DB::table('brands')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Brand 1',
        'slug' => 'brand-1',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $brand2Id = DB::table('brands')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Brand 2',
        'slug' => 'brand-2',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $categoryId = DB::table('categories')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Watches',
        'slug' => 'watches',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('product_models')->insert([
        'public_id' => (string) Str::ulid(),
        'brand_id' => $brand1Id,
        'category_id' => $categoryId,
        'name' => 'Ultra',
        'slug' => 'ultra-brand-1',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('product_models')->insert([
        'public_id' => (string) Str::ulid(),
        'brand_id' => $brand2Id,
        'category_id' => $categoryId,
        'name' => 'Ultra',
        'slug' => 'ultra-brand-2',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $count = DB::table('product_models')->where('name', 'Ultra')->count();
    expect($count)->toBe(2);
});

it('rejects malformed slug via check constraint', function (): void {
    $brandId = DB::table('brands')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Brand Check Test',
        'slug' => 'brand-check-test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $categoryId = DB::table('categories')->insertGetId([
        'public_id' => (string) Str::ulid(),
        'name' => 'Category Check Test',
        'slug' => 'category-check-test',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('product_models')->insert([
        'public_id' => (string) Str::ulid(),
        'brand_id' => $brandId,
        'category_id' => $categoryId,
        'name' => 'Invalid Slug Model',
        'slug' => 'Invalid_Slug!',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rolls back migration cleanly', function (): void {
    expect(Schema::hasTable('product_models'))->toBeTrue();

    try {
        while (Schema::hasTable('product_models')) {
            Artisan::call('migrate:rollback', ['--step' => 1, '--no-interaction' => true]);
        }
        expect(Schema::hasTable('product_models'))->toBeFalse();
    } finally {
        Artisan::call('migrate', ['--no-interaction' => true]);
    }
});
