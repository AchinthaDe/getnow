<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use App\Domain\Content\Enums\MenuItemKind;
use Spatie\LaravelData\Data;

final class UpdateMenuItemInputData extends Data
{
    /**
     * @param  array<int, UpdateMenuItemInputData>  $children
     */
    public function __construct(
        public ?string $public_id,
        public ?string $label = null,
        public ?string $url = null,
        public ?string $icon = null,
        public bool $opens_in_new_tab = false,
        public bool $is_enabled = true,
        public array $children = [],
        public MenuItemKind $kind = MenuItemKind::LINK,
        public ?string $category_public_id = null,
    ) {}
}
