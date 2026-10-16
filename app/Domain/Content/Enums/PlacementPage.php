<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum PlacementPage: string
{
    case GLOBAL_LAYOUT = 'global_layout';
    case HOME = 'home';

    public function label(): string
    {
        return match ($this) {
            self::GLOBAL_LAYOUT => 'Global Layout',
            self::HOME => 'Homepage',
        };
    }
}
