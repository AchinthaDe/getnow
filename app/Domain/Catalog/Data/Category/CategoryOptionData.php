<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data\Category;

use Spatie\LaravelData\Data;

final class CategoryOptionData extends Data
{
    public function __construct(
        public string $public_id,
        public string $name,
        public string $label,
    ) {}
}
