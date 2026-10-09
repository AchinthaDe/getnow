<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('rejects reversed dates at db level', function () {
    expect(fn () => DB::table('content_blocks')->insert([
        'public_id' => '01HQJ3...1',
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'starts_at' => '2026-02-01',
        'ends_at' => '2026-01-01',
    ]))->toThrow(QueryException::class);
});

it('accepts null dates at db level', function () {
    DB::table('content_blocks')->insert([
        'public_id' => (string) Str::ulid(),
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'status' => 'draft',
        'is_enabled' => false,
        'starts_at' => null,
        'ends_at' => null,
    ]);
    expect(true)->toBeTrue();
});

it('rejects schema_version 0 at db level', function () {
    expect(fn () => DB::table('content_blocks')->insert([
        'public_id' => (string) Str::ulid(),
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'schema_version' => 0,
    ]))->toThrow(QueryException::class);
});

it('rejects negative sort_order at db level', function () {
    expect(fn () => DB::table('content_blocks')->insert([
        'public_id' => (string) Str::ulid(),
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'sort_order' => -1,
    ]))->toThrow(QueryException::class);
});

it('rejects bad status at db level', function () {
    expect(fn () => DB::table('content_blocks')->insert([
        'public_id' => (string) Str::ulid(),
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'status' => 'invalid_status',
    ]))->toThrow(QueryException::class);
});
