<?php

declare(strict_types=1);

use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use Illuminate\Support\Carbon;

it('ensures scopeVisible and isLiveAt have exact parity', function () {
    $now = Carbon::parse('2026-10-09 12:00:00');
    Carbon::setTestNow($now);

    // Published, enabled, no dates (live)
    $b1 = ContentBlock::factory()->create([
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    // Draft, enabled, no dates (not live)
    $b2 = ContentBlock::factory()->create([
        'status' => PublishStatus::DRAFT,
        'is_enabled' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    // Published, disabled, no dates (not live)
    $b3 = ContentBlock::factory()->create([
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => false,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    // Published, enabled, starts in future (not live)
    $b4 = ContentBlock::factory()->create([
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => true,
        'starts_at' => $now->copy()->addDay(),
        'ends_at' => null,
    ]);

    // Published, enabled, ends in past (not live)
    $b5 = ContentBlock::factory()->create([
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => true,
        'starts_at' => null,
        'ends_at' => $now->copy()->subDay(),
    ]);

    // Published, enabled, within dates (live)
    $b6 = ContentBlock::factory()->create([
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => true,
        'starts_at' => $now->copy()->subDay(),
        'ends_at' => $now->copy()->addDay(),
    ]);

    $visibleIds = ContentBlock::visible()->pluck('id')->toArray();

    /** @var ContentBlock[] $blocks */
    $blocks = [$b1, $b2, $b3, $b4, $b5, $b6];

    foreach ($blocks as $block) {
        $isLive = $block->isLiveAt($now);
        $inScope = in_array($block->id, $visibleIds, true);

        expect($isLive)->toBe($inScope);
    }
});
