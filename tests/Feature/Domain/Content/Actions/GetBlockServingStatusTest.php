<?php

declare(strict_types=1);

use App\Domain\Content\Actions\ArchiveContentBlock;
use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Data\ServingHealthResult;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\TestBlocks;

uses(RefreshDatabase::class);

beforeEach(function () {
    TestBlocks::register();
});

it('does not call the resolver for not-live rows', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    $resolver = Mockery::mock(ResolveServablePayload::class);
    $resolver->shouldNotReceive('__invoke');
    app()->instance(ResolveServablePayload::class, $resolver);

    $action = app(GetBlockServingStatus::class);
    $result = $action($block);

    expect($result->status)->toBe(ServingStatus::NotLive)
        ->and($result->healthOutcome)->toBeNull();
});

it('returns PayloadError with the right outcome for each broken state', function () {
    // 1. Unknown Type
    /** @var ContentBlock $block1 */
    $block1 = ContentBlock::factory()->published()->unknownType()->create();
    $result1 = app(GetBlockServingStatus::class)($block1);
    expect($result1->status)->toBe(ServingStatus::PayloadError)
        ->and($result1->healthOutcome)->toBe(ServingHealthOutcome::UnknownType);

    // 2. Unsupported Version
    /** @var ContentBlock $block2 */
    $block2 = ContentBlock::factory()->published()->unsupportedVersion()->create();
    $result2 = app(GetBlockServingStatus::class)($block2);
    expect($result2->status)->toBe(ServingStatus::PayloadError)
        ->and($result2->healthOutcome)->toBe(ServingHealthOutcome::UnsupportedVersion);

    // 3. Invalid After Upgrade
    /** @var ContentBlock $block3 */
    $block3 = ContentBlock::factory()->published()->invalidAfterUpgrade()->create();
    $result3 = app(GetBlockServingStatus::class)($block3);
    expect($result3->status)->toBe(ServingStatus::PayloadError)
        ->and($result3->healthOutcome)->toBe(ServingHealthOutcome::InvalidAfterUpgrade);

    // 4. Upgrade Failed
    /** @var ContentBlock $block4 */
    $block4 = ContentBlock::factory()->published()->failedUpgrade()->create();
    $result4 = app(GetBlockServingStatus::class)($block4);
    expect($result4->status)->toBe(ServingStatus::PayloadError)
        ->and($result4->healthOutcome)->toBe(ServingHealthOutcome::UpgradeFailed);
});

it('returns Live for a servable live block', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create();
    $result = app(GetBlockServingStatus::class)($block);

    expect($result->status)->toBe(ServingStatus::Live)
        ->and($result->healthOutcome)->toBe(ServingHealthOutcome::Servable);
});

it('calls the resolver at most once per block per request', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create();

    $resolver = Mockery::mock(ResolveServablePayload::class);
    $resolver->shouldReceive('__invoke')
        ->with($block, false)
        ->once() // Asserts it is called exactly once
        ->andReturn(new ServingHealthResult(ServingHealthOutcome::Servable, null));

    app()->instance(ResolveServablePayload::class, $resolver);

    $action = app(GetBlockServingStatus::class);
    $action($block); // First call
    $action($block); // Second call should hit memo cache
});

it('passes log: false to the resolver', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create();

    $resolver = Mockery::mock(ResolveServablePayload::class);
    $resolver->shouldReceive('__invoke')
        ->with($block, false)
        ->once()
        ->andReturn(new ServingHealthResult(ServingHealthOutcome::Servable, null));

    app()->instance(ResolveServablePayload::class, $resolver);

    app(GetBlockServingStatus::class)($block);
});

it('refreshes status after Publish and Archive within the same request', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();
    $action = app(GetBlockServingStatus::class);

    // Draft -> NotLive
    expect($action($block)->status)->toBe(ServingStatus::NotLive);

    // Publish
    app(PublishContentBlock::class)($block->id);
    $block->refresh();

    // Now it should be Live
    expect($action($block)->status)->toBe(ServingStatus::Live);

    // Archive
    app(ArchiveContentBlock::class)($block->id);
    $block->refresh();

    // Now it should be NotLive again
    expect($action($block)->status)->toBe(ServingStatus::NotLive);
});

it('refreshes status after an update within the same request', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create();
    $action = app(GetBlockServingStatus::class);

    expect($action($block)->status)->toBe(ServingStatus::Live);

    // Travel time so updated_at changes
    Carbon::setTestNow(now()->addSeconds(2));

    // Update block type to be invalid, this updates updated_at
    $block->update(['type' => 'unknown_type_for_testing']);

    // Now it should resolve to PayloadError
    expect($action($block)->status)->toBe(ServingStatus::PayloadError);
});

it('resolves freshly if status changes without updated_at changing', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();
    $action = app(GetBlockServingStatus::class);

    expect($action($block)->status)->toBe(ServingStatus::NotLive);

    $block->status = PublishStatus::PUBLISHED;
    $block->updateQuietly(); // updated_at doesn't change

    // We changed status but not updated_at, should refresh and say Live
    expect($action($block)->status)->toBe(ServingStatus::Live);
});

it('resolves freshly if is_enabled changes without updated_at changing', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->disabled()->create();
    $action = app(GetBlockServingStatus::class);

    expect($action($block)->status)->toBe(ServingStatus::NotLive);

    $block->is_enabled = true;
    $block->updateQuietly(); // updated_at doesn't change

    expect($action($block)->status)->toBe(ServingStatus::Live);
});

it('resolves freshly if payload changes without updated_at changing', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create();
    $action = app(GetBlockServingStatus::class);

    expect($action($block)->status)->toBe(ServingStatus::Live);

    $payload = $block->payload;
    $payload['text'] = null; // Make it invalid
    $block->payload = $payload;
    $block->updateQuietly(); // updated_at doesn't change

    // The payload hash is part of the key, so it should re-resolve to PayloadError
    expect($action($block)->status)->toBe(ServingStatus::PayloadError);
});

it('resolves freshly if starts_at or ends_at changes without updated_at changing', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->scheduled(now()->addDay(), now()->addDays(2))->create();
    $action = app(GetBlockServingStatus::class);

    // Starts in the future, so not live
    expect($action($block)->status)->toBe(ServingStatus::NotLive);

    $block->starts_at = Carbon::now()->subDay();
    $block->updateQuietly(); // updated_at doesn't change

    // Should resolve freshly and see it's Live
    expect($action($block)->status)->toBe(ServingStatus::Live);

    $block->ends_at = Carbon::now()->subMinute();
    $block->updateQuietly(); // updated_at doesn't change

    // Should resolve freshly and see it's NotLive again
    expect($action($block)->status)->toBe(ServingStatus::NotLive);
});
