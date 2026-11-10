<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Brand\ListBrandOptions;
use App\Domain\Catalog\Data\Brand\BrandOptionData;
use Database\Factories\BrandFactory;
use Tests\Support\AssertsQueryCounts;

it('returns only active brands in options list', function (): void {
    BrandFactory::new()->count(3)->create();
    BrandFactory::new()->inactive()->count(2)->create();

    $action = new ListBrandOptions;
    $options = $action();

    expect($options)->toHaveCount(3);
    foreach ($options as $option) {
        expect($option)->toBeInstanceOf(BrandOptionData::class)
            ->and($option->public_id)->toBeString()
            ->and($option->name)->toBeString();
    }
});

it('matches search case-insensitively', function (): void {
    BrandFactory::new()->create(['name' => 'Apple', 'slug' => 'apple']);
    BrandFactory::new()->create(['name' => 'Pineapple', 'slug' => 'pineapple']);
    BrandFactory::new()->create(['name' => 'Banana', 'slug' => 'banana']);

    $action = new ListBrandOptions;
    $options = $action(search: 'appl');

    $names = array_map(fn (BrandOptionData $o) => $o->name, $options);
    expect($names)->toBe(['Apple', 'Pineapple'])
        ->and($names)->not->toContain('Banana');
});

it('escapes LIKE metacharacters percent underscore and backslash', function (): void {
    BrandFactory::new()->create(['name' => '100% Cotton', 'slug' => '100-cotton']);
    BrandFactory::new()->create(['name' => '1000 Cotton', 'slug' => '1000-cotton']);
    BrandFactory::new()->create(['name' => 'A_B', 'slug' => 'a-b']);
    BrandFactory::new()->create(['name' => 'AXB', 'slug' => 'axb']);
    BrandFactory::new()->create(['name' => 'C\\D', 'slug' => 'c-d']);
    BrandFactory::new()->create(['name' => 'CD', 'slug' => 'cd']);

    $action = new ListBrandOptions;

    $percentOptions = $action(search: '%');
    expect(array_map(fn (BrandOptionData $o) => $o->name, $percentOptions))->toBe(['100% Cotton']);

    $underscoreOptions = $action(search: '_');
    expect(array_map(fn (BrandOptionData $o) => $o->name, $underscoreOptions))->toBe(['A_B']);

    $backslashOptions = $action(search: '\\');
    expect(array_map(fn (BrandOptionData $o) => $o->name, $backslashOptions))->toBe(['C\\D']);
});

it('honors default and maximum limit bounds', function (): void {
    BrandFactory::new()->count(60)->create();

    $action = new ListBrandOptions;

    expect($action())->toHaveCount(20)
        ->and($action(limit: 10))->toHaveCount(10)
        ->and($action(limit: 1000))->toHaveCount(50)
        ->and($action(limit: 0))->toHaveCount(1);
});

it('orders options by lower name ascending then id', function (): void {
    BrandFactory::new()->create(['name' => 'Zeta Brand', 'slug' => 'zeta-brand']);
    BrandFactory::new()->create(['name' => 'alpha Brand', 'slug' => 'alpha-brand']);
    BrandFactory::new()->create(['name' => 'Beta Brand', 'slug' => 'beta-brand']);

    $action = new ListBrandOptions;
    $options = $action();

    $names = array_map(fn (BrandOptionData $o) => $o->name, $options);
    expect($names)->toBe(['alpha Brand', 'Beta Brand', 'Zeta Brand']);
});

it('executes in constant single database query as rows grow', function (): void {
    BrandFactory::new()->count(50)->create();

    $withSearch = AssertsQueryCounts::assertCount(1, fn (): array => (new ListBrandOptions)(search: 'a', limit: 20));
    $withoutSearch = AssertsQueryCounts::assertCount(1, fn (): array => (new ListBrandOptions)(search: null, limit: 20));

    expect($withSearch)->toBeArray()
        ->and($withoutSearch)->toBeArray();
});
