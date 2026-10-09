<?php

declare(strict_types=1);

use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Data\ServingHealthResult;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\UnservablePayloadException;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('publishes draft block and logs activity', function () {
    $resolveServablePayload = app(ResolveServablePayload::class);
    $action = new PublishContentBlock($resolveServablePayload);

    $user = User::factory()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create([
        'payload' => ['text' => 'valid', 'tone' => 'info', 'link' => '/'],
    ]);

    $published = $action($block->id, $user);

    expect($published->status)->toBe(PublishStatus::PUBLISHED);

    \Pest\Laravel\assertDatabaseHas('activity_log', [
        'subject_type' => ContentBlock::class,
        'subject_id' => $block->id,
        'causer_id' => $user->id,
        'description' => 'published',
    ]);
});

it('rejects archived blocks', function () {
    $resolveServablePayload = app(ResolveServablePayload::class);
    $action = new PublishContentBlock($resolveServablePayload);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->archived()->create();

    expect(fn () => $action($block->id))
        ->toThrow(IllegalStateTransitionException::class);
});

it('returns immediately if already published', function () {
    $resolveServablePayload = app(ResolveServablePayload::class);
    $action = new PublishContentBlock($resolveServablePayload);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create();

    $updatedAt = $block->updated_at;
    Carbon\Carbon::setTestNow(now()->addSecond());

    $action($block->id);

    expect($block->refresh()->updated_at->timestamp)->toBe($updatedAt->timestamp);

    \Pest\Laravel\assertDatabaseMissing('activity_log', [
        'subject_id' => $block->id,
        'description' => 'published',
    ]);
});

it('throws UnservablePayloadException if stored version is above current and leaves row draft', function () {
    $resolveServablePayload = app(ResolveServablePayload::class);
    $action = new PublishContentBlock($resolveServablePayload);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create([
        'schema_version' => 999,
        'payload' => ['text' => 'valid', 'tone' => 'info', 'link' => '/'],
    ]);

    expect(fn () => $action($block->id))
        ->toThrow(UnservablePayloadException::class);

    expect($block->refresh()->status)->toBe(PublishStatus::DRAFT);
});

it('throws UnservablePayloadException if type is unregistered', function () {
    $resolveServablePayload = app(ResolveServablePayload::class);
    $action = new PublishContentBlock($resolveServablePayload);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create([
        'type' => 'some_unknown_type',
        'schema_version' => 1,
        'payload' => ['text' => 'valid', 'tone' => 'info', 'link' => '/'],
    ]);

    expect(fn () => $action($block->id))
        ->toThrow(UnservablePayloadException::class);
});

it('throws UnservablePayloadException if payload is invalid', function () {
    $resolveServablePayload = app(ResolveServablePayload::class);
    $action = new PublishContentBlock($resolveServablePayload);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create([
        'payload' => ['text' => ''], // invalid empty text
    ]);

    expect(fn () => $action($block->id))
        ->toThrow(UnservablePayloadException::class);
});

it('resolves payload inside a database transaction with a locked model', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    $resolver = Mockery::mock(ResolveServablePayload::class);
    $resolver->shouldReceive('__invoke')
        ->once()
        ->andReturnUsing(function (ContentBlock $resolvedBlock) use ($block) {
            expect(DB::transactionLevel())->toBeGreaterThan(0)
                ->and($resolvedBlock->id)->toBe($block->id);

            return new ServingHealthResult(ServingHealthOutcome::Servable, null);
        });
    app()->instance(ResolveServablePayload::class, $resolver);

    $action = app(PublishContentBlock::class);
    $action($block->id);
});
