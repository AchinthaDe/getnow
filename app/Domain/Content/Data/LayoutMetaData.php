<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Data;

final class LayoutMetaData extends Data
{
    public function __construct(
        public int $api_version,
        public string $revision,
        public string $generated_at,
    ) {}
}
