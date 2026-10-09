<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class UpdateContentBlockData extends Data
{
    public function __construct(
        public ?CarbonInterface $starts_at,
        public ?CarbonInterface $ends_at,
    ) {}
}
