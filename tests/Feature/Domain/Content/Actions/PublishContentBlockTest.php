<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetUpgradedBlockPayload;
use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\InvalidPayloadException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Exceptions\UnsupportedSchemaVersionException;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;

it('publishes draft block and logs activity', function () {
    $getUpgradedBlockPayload = app(GetUpgradedBlockPayload::class);
    $action = new PublishContentBlock($getUpgradedBlockPayload);

    $user = User::factory()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create([
        'payload' => ['text' => 'valid', 'tone' => 'info'],
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
    $getUpgradedBlockPayload = app(GetUpgradedBlockPayload::class);
    $action = new PublishContentBlock($getUpgradedBlockPayload);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->archived()->create();

    expect(fn () => $action($block->id))
        ->toThrow(IllegalStateTransitionException::class);
});

it('returns immediately if already published', function () {
    $getUpgradedBlockPayload = app(GetUpgradedBlockPayload::class);
    $action = new PublishContentBlock($getUpgradedBlockPayload);

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

it('throws UnsupportedSchemaVersionException if stored version is above current and leaves row draft', function () {
    $getUpgradedBlockPayload = app(GetUpgradedBlockPayload::class);
    $action = new PublishContentBlock($getUpgradedBlockPayload);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create([
        'schema_version' => 999,
        'payload' => ['text' => 'valid', 'tone' => 'info'],
    ]);

    expect(fn () => $action($block->id))
        ->toThrow(UnsupportedSchemaVersionException::class);

    expect($block->refresh()->status)->toBe(PublishStatus::DRAFT);
});

it('throws UnknownBlockTypeException if type is unregistered', function () {
    $getUpgradedBlockPayload = app(GetUpgradedBlockPayload::class);
    $action = new PublishContentBlock($getUpgradedBlockPayload);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create([
        'type' => 'some_unknown_type',
        'schema_version' => 1,
        'payload' => ['text' => 'valid', 'tone' => 'info'],
    ]);

    expect(fn () => $action($block->id))
        ->toThrow(UnknownBlockTypeException::class);
});

it('throws InvalidPayloadException if payload is invalid', function () {
    $getUpgradedBlockPayload = app(GetUpgradedBlockPayload::class);
    $action = new PublishContentBlock($getUpgradedBlockPayload);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create([
        'payload' => ['text' => ''], // invalid empty text
    ]);

    expect(fn () => $action($block->id))
        ->toThrow(InvalidPayloadException::class);
});
