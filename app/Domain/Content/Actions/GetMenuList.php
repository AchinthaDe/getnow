<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Data\MenuListData;
use App\Domain\Content\Data\MenuSummaryData;
use App\Domain\Content\Models\Menu;

final class GetMenuList
{
    public function __invoke(): MenuListData
    {
        $menus = Menu::withCount('items')->orderBy('id')->get();

        $summaries = $menus->map(fn (Menu $menu) => new MenuSummaryData(
            public_id: $menu->public_id,
            key: $menu->key,
            label: $menu->label,
            title: $menu->title,
            item_count: (int) $menu->items_count,
            updated_at: $menu->updated_at,
        ))->all();

        return new MenuListData(
            menus: $summaries,
        );
    }
}
