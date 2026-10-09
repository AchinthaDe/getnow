<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetUpgradedBlockPayload;
use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
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

    $action($block->id);

    \Pest\Laravel\assertDatabaseMissing('activity_log', [
        'subject_id' => $block->id,
        'description' => 'published',
    ]);
});
