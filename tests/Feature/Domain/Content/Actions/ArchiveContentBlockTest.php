<?php

declare(strict_types=1);

use App\Domain\Content\Actions\ArchiveContentBlock;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('archives a published enabled block, disables it and logs activity', function () {
    $action = new ArchiveContentBlock;
    $user = User::factory()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => true,
    ]);

    $archived = $action($block->id, $user);

    expect($archived->status)->toBe(PublishStatus::ARCHIVED)
        ->and($archived->is_enabled)->toBeFalse();

    $activity = Activity::where('subject_type', ContentBlock::class)
        ->where('subject_id', $block->id)
        ->latest()
        ->first();

    expect($activity->event)->toBe('archived')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->properties->toArray())->toMatchArray([
            'old_status' => PublishStatus::PUBLISHED->value,
            'new_status' => PublishStatus::ARCHIVED->value,
            'old_is_enabled' => true,
            'new_is_enabled' => false,
        ]);
});

it('archives a draft block and forces it disabled', function () {
    $action = new ArchiveContentBlock;
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => PublishStatus::DRAFT,
        'is_enabled' => true,
    ]);

    $archived = $action($block->id);
    expect($archived->status)->toBe(PublishStatus::ARCHIVED)
        ->and($archived->is_enabled)->toBeFalse();
});

it('archives a published disabled block and keeps it disabled', function () {
    $action = new ArchiveContentBlock;
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => false,
    ]);

    $archived = $action($block->id);
    expect($archived->status)->toBe(PublishStatus::ARCHIVED)
        ->and($archived->is_enabled)->toBeFalse();
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

it('archives an unknown type block successfully', function () {
    $action = new ArchiveContentBlock;
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'some_unknown_type',
        'schema_version' => 1,
    ]);

    $archived = $action($block->id);

    expect($archived->status)->toBe(PublishStatus::ARCHIVED);
});

it('archives a block with invalid payload successfully', function () {
    $action = new ArchiveContentBlock;
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'payload' => ['text' => ''], // invalid empty text
    ]);

    $archived = $action($block->id);

    expect($archived->status)->toBe(PublishStatus::ARCHIVED);
});

it('does not call the resolver during Archive', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => true,
    ]);

    app()->bind(ResolveServablePayload::class, function () {
        throw new Exception('Resolver should not be called');
    });

    $action = app(ArchiveContentBlock::class);
    $action($block->id);
    expect(true)->toBeTrue(); // assertion to count
});
