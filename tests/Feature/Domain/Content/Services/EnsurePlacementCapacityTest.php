<?php

declare(strict_types=1);

use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Exceptions\PlacementCapacityExceededException;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\EnsurePlacementCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Assert;
use Tests\Support\TestBlocks;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    TestBlocks::register();
});

it('allows active block when below placement capacity', function (): void {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);

    $service = new EnsurePlacementCapacity;
    $service->check($block, null, null);

    expect(true)->toBeTrue();
});

it('throws PlacementCapacityExceededException when overlapping singleton block exists', function (): void {
    /** @var ContentBlock $existingBlock */
    $existingBlock = ContentBlock::factory()->published()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    /** @var ContentBlock $secondBlock */
    $secondBlock = ContentBlock::factory()->draft()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);

    $service = new EnsurePlacementCapacity;

    try {
        $service->check($secondBlock, null, null);
        Assert::fail('Expected PlacementCapacityExceededException was not thrown.');
    } catch (PlacementCapacityExceededException $e) {
        expect($e->placement)->toBe(Placement::ANNOUNCEMENT_BAR)
            ->and($e->maxCount)->toBe(1)
            ->and($e->conflictingPublicId)->toBe($existingBlock->public_id);
    }
});

it('allows non-overlapping future schedule in singleton placement', function (): void {
    /** @var ContentBlock $existingBlock */
    $existingBlock = ContentBlock::factory()->published()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'starts_at' => null,
        'ends_at' => now()->addDays(2),
    ]);

    /** @var ContentBlock $secondBlock */
    $secondBlock = ContentBlock::factory()->draft()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);

    $service = new EnsurePlacementCapacity;
    $service->check($secondBlock, now()->addDays(3), null);

    expect(true)->toBeTrue();
});

it('enforces multi-block capacity up to maxCount', function (): void {
    $existing = [];
    for ($i = 0; $i < 5; $i++) {
        $existing[] = ContentBlock::factory()->published()->create([
            'placement' => Placement::HOME_HERO_CAROUSEL->value,
            'type' => 'promo_slide',
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    /** @var ContentBlock $sixthBlock */
    $sixthBlock = ContentBlock::factory()->draft()->create([
        'placement' => Placement::HOME_HERO_CAROUSEL->value,
        'type' => 'promo_slide',
    ]);

    $service = new EnsurePlacementCapacity;

    expect(fn () => $service->check($sixthBlock, null, null))
        ->toThrow(PlacementCapacityExceededException::class);
});
