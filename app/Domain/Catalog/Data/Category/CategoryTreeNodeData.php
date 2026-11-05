<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data\Category;

use Spatie\LaravelData\Data;

final class CategoryTreeNodeData extends Data
{
    /**
     * @param  array<int, CategoryTreeNodeData>  $children
     */
    public function __construct(
        public string $public_id,
        public string $name,
        public string $slug,
        public int $sort_order,
        public bool $show_in_nav,
        public bool $is_active,
        public bool $is_effectively_active,
        public array $children = [],
    ) {}
}
