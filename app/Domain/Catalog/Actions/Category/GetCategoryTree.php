<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Category;

use App\Domain\Catalog\Data\Category\CategoryTreeNodeData;
use App\Domain\Catalog\Enums\CategoryTreeAudience;
use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final class GetCategoryTree
{
    /**
     * @return array<int, CategoryTreeNodeData>
     */
    public function __invoke(CategoryTreeAudience $audience): array
    {
        $query = Category::query()
            ->withEffectiveActive()
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc');

        if ($audience === CategoryTreeAudience::Storefront) {
            $query->effectivelyActive()
                ->where('show_in_nav', true);
        }

        /** @var Collection<int, Category> $categories */
        $categories = $query->get();

        /** @var array<int, list<Category>> $groupedByParent */
        $groupedByParent = [];
        /** @var list<Category> $roots */
        $roots = [];

        foreach ($categories as $cat) {
            if ($cat->parent_id === null) {
                $roots[] = $cat;
            } else {
                $groupedByParent[$cat->parent_id][] = $cat;
            }
        }

        $buildSubtree = function (Category $parent) use (&$buildSubtree, $groupedByParent): CategoryTreeNodeData {
            $childrenNodes = [];
            foreach ($groupedByParent[$parent->id] ?? [] as $child) {
                $childrenNodes[] = $buildSubtree($child);
            }

            return new CategoryTreeNodeData(
                public_id: $parent->public_id,
                name: $parent->name,
                slug: $parent->slug,
                sort_order: $parent->sort_order,
                show_in_nav: $parent->show_in_nav,
                is_active: $parent->is_active,
                is_effectively_active: (bool) $parent->is_effectively_active,
                children: $childrenNodes,
            );
        };

        $tree = [];
        foreach ($roots as $root) {
            $tree[] = $buildSubtree($root);
        }

        return $tree;
    }
}
