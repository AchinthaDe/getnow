<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Data;

final class LayoutMenuItemData extends Data
{
    /**
     * @param  array<int, LayoutMenuItemData>  $children
     */
    public function __construct(
        public string $id,
        public string $label,
        public string $url,
        public ?string $icon,
        public bool $new_tab,
        public array $children = [],
    ) {}
}
