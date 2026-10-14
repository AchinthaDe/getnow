<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use App\Domain\Content\Enums\MenuKey;
use Spatie\LaravelData\Data;

final class MenuTreeData extends Data
{
    /**
     * @param  array<int, MenuItemData>  $items
     */
    public function __construct(
        public string $public_id,
        public MenuKey $key,
        public string $label,
        public ?string $title,
        public array $items,
    ) {}
}
