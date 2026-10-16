<?php

declare(strict_types=1);

use App\Domain\Content\Actions\EnableContentBlock;
use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Exceptions\PlacementCapacityExceededException;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\EnsurePlacementCapacity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('ensures pg_advisory_xact_lock executes before candidate queries in Publish, Enable, and Update actions', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    // 1. PublishContentBlock
    /** @var ContentBlock $blockToPublish */
    $blockToPublish = ContentBlock::factory()->draft()->create();

    $recorder = new class
    {
        /** @var list<string> */
        private array $queries = [];

        public function add(string $sql): void
        {
            $this->queries[] = $sql;
        }

        public function reset(): void
        {
            $this->queries = [];
        }

        /** @return list<string> */
        public function queries(): array
        {
            return $this->queries;
        }
    };

    DB::listen(function ($query) use ($recorder) {
        $recorder->add($query->sql);
    });

    app(PublishContentBlock::class)($blockToPublish->id, $admin);

    $advisoryIndex = null;
    $candidateIndex = null;
    foreach ($recorder->queries() as $i => $sql) {
        if (str_contains(strtolower($sql), 'pg_advisory_xact_lock') && $advisoryIndex === null) {
            $advisoryIndex = $i;
        }
        if (str_contains(strtolower($sql), 'placement') && str_contains(strtolower($sql), 'status') && str_contains(strtolower($sql), 'for update') && $advisoryIndex !== null && $candidateIndex === null) {
            $candidateIndex = $i;
        }
    }

    expect($advisoryIndex)->not->toBeNull()
        ->and($candidateIndex)->not->toBeNull()
        ->and($advisoryIndex)->toBeLessThan($candidateIndex);

    $blockToPublish->delete();

    // 2. EnableContentBlock
    /** @var ContentBlock $blockToEnable */
    $blockToEnable = ContentBlock::factory()->published()->disabled()->create();

    $recorder->reset();
    app(EnableContentBlock::class)($blockToEnable->id, $admin);

    $advisoryIndex = null;
    $candidateIndex = null;
    foreach ($recorder->queries() as $i => $sql) {
        if (str_contains(strtolower($sql), 'pg_advisory_xact_lock') && $advisoryIndex === null) {
            $advisoryIndex = $i;
        }
        if (str_contains(strtolower($sql), 'placement') && str_contains(strtolower($sql), 'status') && str_contains(strtolower($sql), 'for update') && $advisoryIndex !== null && $candidateIndex === null) {
            $candidateIndex = $i;
        }
    }

    expect($advisoryIndex)->not->toBeNull()
        ->and($candidateIndex)->not->toBeNull()
        ->and($advisoryIndex)->toBeLessThan($candidateIndex);

    $blockToEnable->delete();

    // 3. UpdateContentBlock
    /** @var ContentBlock $blockToUpdate */
    $blockToUpdate = ContentBlock::factory()->published()->create([
        'starts_at' => now()->addDays(5),
    ]);

    $recorder->reset();
    app(UpdateContentBlock::class)(
        $blockToUpdate->id,
        new UpdateContentBlockData(starts_at: now()->addDays(6), ends_at: null),
        $blockToUpdate->payload,
        $admin
    );

    $advisoryIndex = null;
    $candidateIndex = null;
    foreach ($recorder->queries() as $i => $sql) {
        if (str_contains(strtolower($sql), 'pg_advisory_xact_lock') && $advisoryIndex === null) {
            $advisoryIndex = $i;
        }
        if (str_contains(strtolower($sql), 'placement') && str_contains(strtolower($sql), 'status') && str_contains(strtolower($sql), 'for update') && $advisoryIndex !== null && $candidateIndex === null) {
            $candidateIndex = $i;
        }
    }

    expect($advisoryIndex)->not->toBeNull()
        ->and($candidateIndex)->not->toBeNull()
        ->and($advisoryIndex)->toBeLessThan($candidateIndex);
});

it('allows staggered windows on max-4 placement when concurrency does not exceed 4', function (): void {
    $service = new EnsurePlacementCapacity;

    ContentBlock::factory()->published()->create([
        'placement' => Placement::HOME_SERVICE_STRIP->value,
        'type' => 'service_item',
        'starts_at' => now(),
        'ends_at' => now()->addDays(10),
    ]);
    ContentBlock::factory()->published()->create([
        'placement' => Placement::HOME_SERVICE_STRIP->value,
        'type' => 'service_item',
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(12),
    ]);
    ContentBlock::factory()->published()->create([
        'placement' => Placement::HOME_SERVICE_STRIP->value,
        'type' => 'service_item',
        'starts_at' => now()->addDays(4),
        'ends_at' => now()->addDays(14),
    ]);

    /** @var ContentBlock $fourthBlock */
    $fourthBlock = ContentBlock::factory()->draft()->create([
        'placement' => Placement::HOME_SERVICE_STRIP->value,
        'type' => 'service_item',
    ]);

    $service->check($fourthBlock, now()->addDays(6), now()->addDays(16));

    expect(true)->toBeTrue();
});

it('rejects 5th overlapping block when maxCount is 4', function (): void {
    $service = new EnsurePlacementCapacity;

    for ($i = 0; $i < 4; $i++) {
        ContentBlock::factory()->published()->create([
            'placement' => Placement::HOME_SERVICE_STRIP->value,
            'type' => 'service_item',
            'starts_at' => now()->addDays($i),
            'ends_at' => now()->addDays(10 + $i),
        ]);
    }

    /** @var ContentBlock $fifthBlock */
    $fifthBlock = ContentBlock::factory()->draft()->create([
        'placement' => Placement::HOME_SERVICE_STRIP->value,
        'type' => 'service_item',
    ]);

    expect(fn () => $service->check($fifthBlock, now()->addDays(6), now()->addDays(8)))
        ->toThrow(PlacementCapacityExceededException::class);
});

it('ignores expired schedule window and passes early', function (): void {
    $service = new EnsurePlacementCapacity;

    ContentBlock::factory()->published()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    /** @var ContentBlock $expiredBlock */
    $expiredBlock = ContentBlock::factory()->draft()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);

    $service->check($expiredBlock, now()->subDays(5), now()->subMinute());

    expect(true)->toBeTrue();
});

it('ignores draft, disabled, and archived blocks in concurrency calculations', function (): void {
    $service = new EnsurePlacementCapacity;

    ContentBlock::factory()->draft()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);
    ContentBlock::factory()->published()->disabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);
    ContentBlock::factory()->archived()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);

    /** @var ContentBlock $targetBlock */
    $targetBlock = ContentBlock::factory()->draft()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);

    $service->check($targetBlock, null, null);

    expect(true)->toBeTrue();
});

it('excludes target block itself from candidate count on update', function (): void {
    $service = new EnsurePlacementCapacity;

    /** @var ContentBlock $targetBlock */
    $targetBlock = ContentBlock::factory()->published()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'starts_at' => now(),
        'ends_at' => null,
    ]);

    $service->check($targetBlock, now()->addDay(), now()->addDays(5));

    expect(true)->toBeTrue();
});
