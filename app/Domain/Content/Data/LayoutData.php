<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Data;

final class LayoutData extends Data
{
    /**
     * @param  array<string, array<int, LayoutBlockData>>  $placements
     * @param  array<string, LayoutMenuData>  $menus
     */
    public function __construct(
        public LayoutSettingsData $settings,
        public array $placements,
        public array $menus,
    ) {}
}
