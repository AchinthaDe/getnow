<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Data;

final class LayoutMenuData extends Data
{
    /**
     * @param  array<int, LayoutMenuItemData>  $items
     */
    public function __construct(
        public ?string $title,
        public array $items,
    ) {}
}
