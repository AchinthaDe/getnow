<?php

declare(strict_types=1);

use App\Domain\Content\Actions\ArchiveContentBlock;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;

it('archives block and logs activity', function () {
    $action = new ArchiveContentBlock;
    $user = User::factory()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create();

    $archived = $action($block->id, $user);

    expect($archived->status)->toBe(PublishStatus::ARCHIVED);

    \Pest\Laravel\assertDatabaseHas('activity_log', [
        'subject_type' => ContentBlock::class,
        'subject_id' => $block->id,
        'causer_id' => $user->id,
        'description' => 'archived',
    ]);
});

it('returns immediately if already archived', function () {
    $action = new ArchiveContentBlock;
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->archived()->create();

    $action($block->id);

    \Pest\Laravel\assertDatabaseMissing('activity_log', [
        'subject_id' => $block->id,
        'description' => 'archived',
    ]);
});
