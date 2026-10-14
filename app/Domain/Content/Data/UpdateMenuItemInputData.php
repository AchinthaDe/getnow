<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Data;

final class UpdateMenuItemInputData extends Data
{
    /**
     * @param  array<int, UpdateMenuItemInputData>  $children
     */
    public function __construct(
        public ?string $public_id,
        public string $label,
        public string $url,
        public ?string $icon = null,
        public bool $opens_in_new_tab = false,
        public bool $is_enabled = true,
        public array $children = [],
    ) {}
}
