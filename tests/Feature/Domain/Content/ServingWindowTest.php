<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\TestBlocks;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    TestBlocks::register();
});

it('evaluates published scheduled block as not live before starts_at and live at starts_at without database write', function (): void {
    Carbon::setTestNow('2026-10-15 10:00:00');

    $startsAt = now()->addHour(); // 11:00:00
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->scheduled($startsAt, null)->create();
    $originalUpdatedAt = $block->updated_at?->timestamp;

    $action = app(GetBlockServingStatus::class);

    // 1. Initial pre-window check
    expect($action($block)->status)->toBe(ServingStatus::NotLive)
        ->and(ContentBlock::visible()->whereKey($block->id)->exists())->toBeFalse();

    // 2. Travel to 1 second before starts_at
    Carbon::setTestNow($startsAt->clone()->subSecond());
    expect($action($block)->status)->toBe(ServingStatus::NotLive);

    // 3. Travel to exact starts_at
    Carbon::setTestNow($startsAt);
    expect($action($block)->status)->toBe(ServingStatus::Live)
        ->and(ContentBlock::visible()->whereKey($block->id)->exists())->toBeTrue();

    // 4. Verify no database write occurred
    $block->refresh();
    expect($block->updated_at?->timestamp)->toBe($originalUpdatedAt);

    // 5. Verify zero activity log rows created for subject
    expect(Activity::forSubject($block)->count())->toBe(0);
});

it('evaluates serving status correctly across time without cache flush under array cache store', function (): void {
    Carbon::setTestNow('2026-10-15 10:00:00');

    $startsAt = now()->addMinutes(10);
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->scheduled($startsAt, null)->create();

    $action = app(GetBlockServingStatus::class);

    // Prime cache before starts_at
    expect($action($block)->status)->toBe(ServingStatus::NotLive);

    // Advance time without flushing array cache
    Carbon::setTestNow($startsAt);

    // Must evaluate to Live dynamically
    expect($action($block)->status)->toBe(ServingStatus::Live);
});

it('evaluates block as not live once ends_at timestamp has passed', function (): void {
    Carbon::setTestNow('2026-10-15 10:00:00');

    $startsAt = now()->subHour();
    $endsAt = now()->addHour();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->scheduled($startsAt, $endsAt)->create();

    $action = app(GetBlockServingStatus::class);

    // Prime cache while live
    expect($action($block)->status)->toBe(ServingStatus::Live);

    // Advance time to 1 second past ends_at
    Carbon::setTestNow($endsAt->clone()->addSecond());

    expect($action($block)->status)->toBe(ServingStatus::NotLive);
});
