<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data\Brand;

use Spatie\LaravelData\Data;

final class BrandOptionData extends Data
{
    public function __construct(
        public string $public_id,
        public string $name,
    ) {}
}
