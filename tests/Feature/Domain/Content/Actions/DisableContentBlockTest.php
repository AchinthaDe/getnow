<?php

declare(strict_types=1);

use App\Domain\Content\Actions\DisableContentBlock;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('disables a block and logs activity with properties', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->enabled()->create();
    $causer = User::factory()->create();

    $action = app(DisableContentBlock::class);
    $action($block->id, $causer);

    $block->refresh();
    expect($block->is_enabled)->toBeFalse()
        ->and($block->updated_by)->toBe($causer->id);

    $log = Activity::latest()->first();
    expect($log->event)->toBe('disabled')
        ->and($log->subject_id)->toBe($block->id)
        ->and($log->causer_id)->toBe($causer->id)
        ->and($log->properties->toArray())->toEqual([
            'old_is_enabled' => true,
            'new_is_enabled' => false,
        ]);
});

it('works with a null causer', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->enabled()->create();
    app(DisableContentBlock::class)($block->id);
    expect($block->refresh()->is_enabled)->toBeFalse();
});

it('is a no-op if already disabled and writes no log', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->disabled()->create();
    $updatedAt = $block->updated_at;

    app(DisableContentBlock::class)($block->id);

    expect($block->refresh()->updated_at->timestamp)->toBe($updatedAt->timestamp);
    expect(Activity::count())->toBe(0);
});

it('throws if attempting to disable an archived block', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->archived()->create();
    app(DisableContentBlock::class)($block->id);
})->throws(IllegalStateTransitionException::class);

it('does not call the resolver during Disable', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->enabled()->create();

    app()->bind(ResolveServablePayload::class, function () {
        throw new Exception('Resolver should not be called');
    });

    $action = app(DisableContentBlock::class);
    $action($block->id);
    expect(true)->toBeTrue();
});
