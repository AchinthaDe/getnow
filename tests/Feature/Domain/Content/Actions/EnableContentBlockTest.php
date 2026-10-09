<?php

declare(strict_types=1);

use App\Domain\Content\Actions\EnableContentBlock;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('enables a block and logs activity with properties', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->disabled()->create();
    $causer = User::factory()->create();

    $action = app(EnableContentBlock::class);
    $action($block->id, $causer);

    $block->refresh();
    expect($block->is_enabled)->toBeTrue()
        ->and($block->updated_by)->toBe($causer->id);

    $log = Activity::latest()->first();
    expect($log->event)->toBe('enabled')
        ->and($log->subject_id)->toBe($block->id)
        ->and($log->causer_id)->toBe($causer->id)
        ->and($log->properties->toArray())->toEqual([
            'old_is_enabled' => false,
            'new_is_enabled' => true,
        ]);
});

it('works with a null causer', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->disabled()->create();
    app(EnableContentBlock::class)($block->id);
    expect($block->refresh()->is_enabled)->toBeTrue();
});

it('is a no-op if already enabled and writes no log', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->enabled()->create();
    $updatedAt = $block->updated_at;

    app(EnableContentBlock::class)($block->id);

    expect($block->refresh()->updated_at->timestamp)->toBe($updatedAt->timestamp);
    expect(Activity::count())->toBe(0);
});

it('throws if attempting to enable an archived block', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->archived()->create();
    app(EnableContentBlock::class)($block->id);
})->throws(IllegalStateTransitionException::class);
