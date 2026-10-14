<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Data\MenuItemData;
use App\Domain\Content\Data\MenuTreeData;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;

final class GetMenuTree
{
    public function __invoke(Menu|MenuKey|string|int $menuIdentifier): MenuTreeData
    {
        $menu = match (true) {
            $menuIdentifier instanceof Menu => $menuIdentifier,
            $menuIdentifier instanceof MenuKey => Menu::where('key', $menuIdentifier->value)->firstOrFail(),
            is_int($menuIdentifier) => Menu::where('id', $menuIdentifier)->firstOrFail(),
            is_string($menuIdentifier) => Menu::where('public_id', $menuIdentifier)
                ->orWhere('key', $menuIdentifier)
                ->firstOrFail(),
        };

        /** @var Collection<int, MenuItem> $items */
        $items = $menu->items()->orderBy('sort_order')->get();

        $childrenByParentId = [];
        $rootItems = [];

        foreach ($items as $item) {
            if ($item->parent_id !== null) {
                $childrenByParentId[$item->parent_id][] = $item;
            } else {
                $rootItems[] = $item;
            }
        }

        $itemDataList = [];
        foreach ($rootItems as $rootItem) {
            $childItems = $childrenByParentId[$rootItem->id] ?? [];
            $childDataList = [];
            foreach ($childItems as $childItem) {
                $childDataList[] = new MenuItemData(
                    public_id: $childItem->public_id,
                    label: $childItem->label,
                    url: $childItem->url,
                    icon: $childItem->icon,
                    opens_in_new_tab: $childItem->opens_in_new_tab,
                    is_enabled: $childItem->is_enabled,
                    sort_order: $childItem->sort_order,
                    children: [],
                );
            }

            $itemDataList[] = new MenuItemData(
                public_id: $rootItem->public_id,
                label: $rootItem->label,
                url: $rootItem->url,
                icon: $rootItem->icon,
                opens_in_new_tab: $rootItem->opens_in_new_tab,
                is_enabled: $rootItem->is_enabled,
                sort_order: $rootItem->sort_order,
                children: $childDataList,
            );
        }

        return new MenuTreeData(
            public_id: $menu->public_id,
            key: $menu->key,
            label: $menu->label,
            title: $menu->title,
            items: $itemDataList,
        );
    }
}
