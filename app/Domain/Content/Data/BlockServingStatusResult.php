<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Enums\ServingStatus;
use Spatie\LaravelData\Data;

final class BlockServingStatusResult extends Data
{
    public function __construct(
        public readonly ServingStatus $status,
        public readonly ?ServingHealthOutcome $healthOutcome = null,
    ) {}
}
