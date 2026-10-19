<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use App\Domain\Content\Enums\Placement;
use Spatie\LaravelData\Data;

final class ContentBlockPreviewData extends Data
{
    public function __construct(
        public readonly ?LayoutBlockData $block,
        public readonly ?Placement $placement,
        public readonly ?LayoutData $layout,
        public readonly bool $usingFallbackPayload,
        public readonly bool $isUnservable,
    ) {}
}
