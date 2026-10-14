<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Data;

final class UpdateMenuData extends Data
{
    /**
     * @param  array<int, UpdateMenuItemInputData>  $items
     */
    public function __construct(
        public ?string $title,
        public array $items = [],
    ) {}
}
