<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use App\Domain\Content\Enums\ServingHealthOutcome;
use Spatie\LaravelData\Data;

final class ServingHealthResult extends Data
{
    public function __construct(
        public readonly ServingHealthOutcome $outcome,
        public readonly ?BlockPayload $payload = null,
    ) {}

    public function isServable(): bool
    {
        return $this->outcome === ServingHealthOutcome::Servable;
    }
}
