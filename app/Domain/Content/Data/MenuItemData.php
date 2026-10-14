<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Data;

final class MenuItemData extends Data
{
    /**
     * @param  array<int, MenuItemData>  $children
     */
    public function __construct(
        public string $public_id,
        public string $label,
        public string $url,
        public ?string $icon,
        public bool $opens_in_new_tab,
        public bool $is_enabled,
        public int $sort_order,
        public array $children = [],
    ) {}
}
