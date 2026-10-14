<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use Spatie\LaravelData\Data;

final class MenuListData extends Data
{
    /**
     * @param  array<int, MenuSummaryData>  $menus
     */
    public function __construct(
        public array $menus,
    ) {}
}
