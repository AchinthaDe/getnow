<?php

declare(strict_types=1);

use App\Domain\Content\Actions\ArchiveContentBlock;
use App\Domain\Content\Actions\DisableContentBlock;
use App\Domain\Content\Actions\EnableContentBlock;
use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Exceptions\UnservablePayloadException;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;
use PHPUnit\Framework\Assert;
use Spatie\Activitylog\Models\Activity;

dataset('unservable_blocks', function () {
    return [
        'Unknown Type' => [
            fn () => ContentBlock::factory()->create([
                'status' => PublishStatus::DRAFT,
                'is_enabled' => false,
                'type' => 'unknown_type',
                'schema_version' => 1,
                'payload' => [],
            ]),
            ServingHealthOutcome::UnknownType,
        ],
        'Invalid After Upgrade' => [
            fn () => ContentBlock::factory()->create([
                'status' => PublishStatus::DRAFT,
                'is_enabled' => false,
                'type' => 'announcement_bar',
                'schema_version' => 1,
                'payload' => ['text' => ''],
            ]),
            ServingHealthOutcome::InvalidAfterUpgrade,
        ],
        'Unsupported Version' => [
            fn () => ContentBlock::factory()->create([
                'status' => PublishStatus::DRAFT,
                'is_enabled' => false,
                'type' => 'announcement_bar',
                'schema_version' => 999,
                'payload' => ['text' => 'valid', 'tone' => 'info', 'link' => '/'],
            ]),
            ServingHealthOutcome::UnsupportedVersion,
        ],
    ];
});

it('Publish rejects unservable payloads without mutating DB', function (Closure $blockFactory, ServingHealthOutcome $outcome) {
    /** @var ContentBlock $block */
    $block = $blockFactory();
    $action = app(PublishContentBlock::class);
    $user = User::factory()->create();

    $activityCount = Activity::count();

    try {
        $action($block->id, $user);
        Assert::fail('Expected UnservablePayloadException was not thrown.');
    } catch (UnservablePayloadException $e) {
        expect($e->outcome)->toBe($outcome);
    }

    $block->refresh();
    expect($block->status)->toBe(PublishStatus::DRAFT) // DB unchanged
        ->and(Activity::count())->toBe($activityCount); // No activity row
})->with('unservable_blocks');

it('Enable rejects unservable payloads without mutating DB', function (Closure $blockFactory, ServingHealthOutcome $outcome) {
    /** @var ContentBlock $block */
    $block = $blockFactory();
    $action = app(EnableContentBlock::class);
    $user = User::factory()->create();

    // We need block to be published to enable it
    $block->update(['status' => PublishStatus::PUBLISHED]);

    $activityCount = Activity::count();

    try {
        $action($block->id, $user);
        Assert::fail('Expected UnservablePayloadException was not thrown.');
    } catch (UnservablePayloadException $e) {
        expect($e->outcome)->toBe($outcome);
    }

    $block->refresh();
    expect($block->is_enabled)->toBeFalse()
        ->and(Activity::count())->toBe($activityCount);
})->with('unservable_blocks');

it('Publish and Enable succeed on servable payloads', function () {
    $user = User::factory()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => PublishStatus::DRAFT,
        'is_enabled' => false,
        'type' => 'announcement_bar',
        'schema_version' => 1,
        'payload' => ['text' => 'valid', 'tone' => 'info', 'link' => 'https://example.com'],
    ]);

    $publish = app(PublishContentBlock::class);
    $publish($block->id, $user);
    $block->refresh();
    expect($block->status)->toBe(PublishStatus::PUBLISHED);

    $enable = app(EnableContentBlock::class);
    $enable($block->id, $user);
    $block->refresh();
    expect($block->is_enabled)->toBeTrue();
});

it('Disable and Archive succeed on unservable payloads', function (Closure $blockFactory) {
    /** @var ContentBlock $block */
    $block = $blockFactory();
    $user = User::factory()->create();
    $block->update(['status' => PublishStatus::PUBLISHED, 'is_enabled' => true]);

    $disable = app(DisableContentBlock::class);
    $disable($block->id, $user);

    $block->refresh();
    expect($block->is_enabled)->toBeFalse();

    $archive = app(ArchiveContentBlock::class);
    $archive($block->id, $user);

    $block->refresh();
    expect($block->status)->toBe(PublishStatus::ARCHIVED);
})->with('unservable_blocks');

it('isLive never calls registry or upgrader', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => true,
    ]);

    $registrySpy = Mockery::spy(BlockRegistry::class);
    app()->instance(BlockRegistry::class, $registrySpy);

    $isLive = $block->isLive();
    expect($isLive)->toBeTrue();

    $registrySpy->shouldNotHaveReceived('get');
});
