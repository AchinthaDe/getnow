<?php

declare(strict_types=1);

namespace App\Domain\Content\Services;

use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\PlacementCapacityExceededException;
use App\Domain\Content\Models\ContentBlock;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class EnsurePlacementCapacity
{
    /**
     * Evaluates concurrent active schedule overlaps within the placement.
     * Throws PlacementCapacityExceededException if activating or updating $targetBlock exceeds $placement->maxCount().
     *
     * @throws PlacementCapacityExceededException
     */
    public function check(
        ContentBlock $targetBlock,
        ?CarbonInterface $startsAt,
        ?CarbonInterface $endsAt
    ): void {
        $placement = $targetBlock->placement;
        if ($placement === null) {
            return;
        }

        $now = now();
        if ($endsAt !== null && $endsAt->lte($now)) {
            return;
        }

        $targetStart = $startsAt !== null ? max($startsAt->getTimestamp(), $now->getTimestamp()) : $now->getTimestamp();
        $targetEnd = $endsAt !== null ? $endsAt->getTimestamp() : PHP_INT_MAX;

        /** @var Collection<int, ContentBlock> $existingBlocks */
        $existingBlocks = ContentBlock::query()
            ->where('placement', $placement->value)
            ->where('id', '!=', $targetBlock->id)
            ->where('status', PublishStatus::PUBLISHED->value)
            ->where('is_enabled', true)
            ->where(function (Builder $q) use ($now): void {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', $now);
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        $overlappingBlocks = [];
        $existingIntervals = [];

        foreach ($existingBlocks as $existing) {
            $existStart = $existing->starts_at !== null ? max($existing->starts_at->getTimestamp(), $now->getTimestamp()) : $now->getTimestamp();
            $existEnd = $existing->ends_at !== null ? $existing->ends_at->getTimestamp() : PHP_INT_MAX;

            if (max($targetStart, $existStart) < min($targetEnd, $existEnd)) {
                $overlappingBlocks[] = $existing;
                $existingIntervals[] = [
                    'block' => $existing,
                    'start' => $existStart,
                    'end' => $existEnd,
                ];
            }
        }

        $maxCount = $placement->maxCount();

        if ($maxCount === 1) {
            if (count($overlappingBlocks) >= 1) {
                throw new PlacementCapacityExceededException(
                    placement: $placement,
                    maxCount: 1,
                    conflictingPublicId: $overlappingBlocks[0]->public_id,
                );
            }

            return;
        }

        // Multi-block capacity evaluation (maxCount > 1)
        $boundaryTimestamps = [$targetStart];
        foreach ($existingIntervals as $interval) {
            if ($interval['start'] >= $targetStart && $interval['start'] < $targetEnd) {
                $boundaryTimestamps[] = $interval['start'];
            }
        }
        $boundaryTimestamps = array_unique($boundaryTimestamps);
        sort($boundaryTimestamps);

        foreach ($boundaryTimestamps as $t) {
            $activeCount = 1; // Count targetBlock
            $conflictingBlockId = null;

            foreach ($existingIntervals as $interval) {
                if ($interval['start'] <= $t && $t < $interval['end']) {
                    $activeCount++;
                    $conflictingBlockId = $interval['block']->public_id;
                    if ($activeCount > $maxCount) {
                        throw new PlacementCapacityExceededException(
                            placement: $placement,
                            maxCount: $maxCount,
                            conflictingPublicId: $conflictingBlockId,
                        );
                    }
                }
            }
        }
    }
}
