<?php

declare(strict_types=1);

namespace App\Filament\Blocks;

use Filament\Schemas\Components\Component;

interface BlockForm
{
    /**
     * @return array<int, Component>
     */
    public static function schema(): array;
}
