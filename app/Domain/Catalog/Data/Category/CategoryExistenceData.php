<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data\Category;

use Spatie\LaravelData\Data;

final class CategoryExistenceData extends Data
{
    public function __construct(
        public string $public_id,
    ) {}
}
