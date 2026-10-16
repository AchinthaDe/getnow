<?php

declare(strict_types=1);

namespace App\Domain\Content\Exceptions;

use App\Domain\Content\Enums\Placement;

final class PlacementCapacityExceededException extends ContentException
{
    public function __construct(
        public readonly Placement $placement,
        public readonly int $maxCount,
        public readonly string $conflictingPublicId,
        string $message = '',
    ) {
        $resolvedMessage = $message !== ''
            ? $message
            : "Placement [{$placement->label()}] allows at most {$maxCount} live block(s). Conflicting block: [{$conflictingPublicId}].";

        parent::__construct($resolvedMessage);
    }
}
