<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Catalog\Data\Category\CategoryTreeNodeData;
use App\Domain\Content\Data\LayoutMenuItemData;
use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Models\MenuItem;
use Illuminate\Support\Collection;

final class ProjectMenuLayoutItems
{
    /**
     * @param  Collection<int, MenuItem>|array<int, MenuItem>  $rootItems
     * @param  array<int, CategoryTreeNodeData>  $categoryRoots
     * @param  array<string, CategoryTreeNodeData>  $categoriesByPublicId
     * @return array<int, LayoutMenuItemData>
     */
    public function __invoke(
        iterable $rootItems,
        array $categoryRoots = [],
        array $categoriesByPublicId = [],
    ): array {
        $result = [];

        foreach ($rootItems as $item) {
            if (! $item->is_enabled || $item->parent_id !== null) {
                continue;
            }

            if ($item->kind === MenuItemKind::LINK) {
                if ($item->url === null || $item->label === null) {
                    continue;
                }

                $mappedChildren = [];
                foreach ($item->children as $child) {
                    $projectedChild = $this->projectChildItem($child, $categoriesByPublicId);
                    if ($projectedChild !== null) {
                        $mappedChildren[] = $projectedChild;
                    }
                }

                $result[] = new LayoutMenuItemData(
                    id: $item->public_id,
                    label: $item->label,
                    url: $item->url,
                    icon: $item->icon,
                    new_tab: (bool) $item->opens_in_new_tab,
                    children: $mappedChildren,
                );
            } elseif ($item->kind === MenuItemKind::CATEGORY) {
                if ($item->category_public_id === null || ! isset($categoriesByPublicId[$item->category_public_id])) {
                    continue;
                }

                $categoryNode = $categoriesByPublicId[$item->category_public_id];
                $label = $item->label !== null ? $item->label : $categoryNode->name;
                $url = '/shop?category='.rawurlencode($categoryNode->slug);

                $mappedChildren = [];
                foreach ($item->children as $child) {
                    $projectedChild = $this->projectChildItem($child, $categoriesByPublicId);
                    if ($projectedChild !== null) {
                        $mappedChildren[] = $projectedChild;
                    }
                }

                $result[] = new LayoutMenuItemData(
                    id: $item->public_id,
                    label: $label,
                    url: $url,
                    icon: $item->icon,
                    new_tab: (bool) $item->opens_in_new_tab,
                    children: $mappedChildren,
                );
            } elseif ($item->kind === MenuItemKind::CATEGORY_TREE) {
                if ($item->category_public_id === null) {
                    foreach ($categoryRoots as $categoryRoot) {
                        $result[] = $this->projectCategoryTreeNode($categoryRoot);
                    }
                } else {
                    $targetNode = $categoriesByPublicId[$item->category_public_id] ?? null;
                    if ($targetNode !== null) {
                        foreach ($targetNode->children as $childNode) {
                            $result[] = $this->projectCategoryTreeNode($childNode);
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param  array<string, CategoryTreeNodeData>  $categoriesByPublicId
     */
    private function projectChildItem(MenuItem $child, array $categoriesByPublicId): ?LayoutMenuItemData
    {
        if (! $child->is_enabled) {
            return null;
        }

        if ($child->kind === MenuItemKind::LINK) {
            if ($child->url === null || $child->label === null) {
                return null;
            }

            return new LayoutMenuItemData(
                id: $child->public_id,
                label: $child->label,
                url: $child->url,
                icon: $child->icon,
                new_tab: (bool) $child->opens_in_new_tab,
                children: [],
            );
        }

        if ($child->kind === MenuItemKind::CATEGORY) {
            if ($child->category_public_id === null || ! isset($categoriesByPublicId[$child->category_public_id])) {
                return null;
            }

            $categoryNode = $categoriesByPublicId[$child->category_public_id];
            $label = $child->label !== null ? $child->label : $categoryNode->name;
            $url = '/shop?category='.rawurlencode($categoryNode->slug);

            return new LayoutMenuItemData(
                id: $child->public_id,
                label: $label,
                url: $url,
                icon: $child->icon,
                new_tab: (bool) $child->opens_in_new_tab,
                children: [],
            );
        }

        return null;
    }

    private function projectCategoryTreeNode(CategoryTreeNodeData $node): LayoutMenuItemData
    {
        $children = [];
        foreach ($node->children as $childNode) {
            $children[] = $this->projectCategoryTreeNode($childNode);
        }

        return new LayoutMenuItemData(
            id: $node->public_id,
            label: $node->name,
            url: '/shop?category='.rawurlencode($node->slug),
            icon: null,
            new_tab: false,
            children: $children,
        );
    }
}
