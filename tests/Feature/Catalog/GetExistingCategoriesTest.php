<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\Category\GetExistingCategories;
use App\Domain\Catalog\Data\Category\CategoryExistenceData;
use Database\Factories\CategoryFactory;
use Illuminate\Support\Str;
use Tests\Support\AssertsQueryCounts;

it('returns empty array when input publicIds is empty without querying database', function (): void {
    $results = AssertsQueryCounts::assertCount(0, fn () => (new GetExistingCategories)([]));
    expect($results)->toBe([]);
});

it('deduplicates publicIds and filters whitespace before executing query', function (): void {
    $cat = CategoryFactory::new()->root()->create();

    $results = (new GetExistingCategories)([$cat->public_id, $cat->public_id, '  ', '']);

    expect($results)->toHaveCount(1)
        ->and($results[0])->toBeInstanceOf(CategoryExistenceData::class)
        ->and($results[0]->public_id)->toBe($cat->public_id);
});

it('executes GetExistingCategories in one query returning existing public IDs as Data', function (): void {
    $cat1 = CategoryFactory::new()->root()->create(['name' => 'Cat 1']);
    $cat2 = CategoryFactory::new()->root()->create(['name' => 'Cat 2']);
    $cat3 = CategoryFactory::new()->root()->create(['name' => 'Cat 3']);
    $unknownUlid = (string) Str::ulid();

    /** @var array<int, CategoryExistenceData> $results */
    $results = AssertsQueryCounts::assertCount(
        1,
        fn (): array => (new GetExistingCategories)([$cat1->public_id, $cat2->public_id, $cat3->public_id, $unknownUlid])
    );

    expect($results)->toHaveCount(3);
    $publicIds = array_map(fn (CategoryExistenceData $data): string => $data->public_id, $results);
    expect($publicIds)->toContain($cat1->public_id, $cat2->public_id, $cat3->public_id)
        ->and($publicIds)->not->toContain($unknownUlid);
});
