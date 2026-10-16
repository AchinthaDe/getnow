<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;

final class CountLiveBlocksForPlacement
{
    public function __invoke(Placement $placement): int
    {
        return ContentBlock::query()
            ->where('placement', $placement->value)
            ->where('status', PublishStatus::PUBLISHED->value)
            ->where('is_enabled', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->count();
    }
}
