<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use App\Domain\Content\Enums\Placement;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class CreateContentBlockData extends Data
{
    public function __construct(
        public Placement $placement,
        public string $type,
        public ?CarbonInterface $starts_at,
        public ?CarbonInterface $ends_at,
    ) {}
}
