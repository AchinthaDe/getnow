<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Category;

use App\Domain\Catalog\Data\Category\CategoryOptionData;
use App\Domain\Catalog\Data\Category\CategoryTreeNodeData;
use App\Domain\Catalog\Enums\CategoryTreeAudience;

final class ListCategoryOptions
{
    public function __construct(
        private readonly GetCategoryTree $getCategoryTree = new GetCategoryTree,
    ) {}

    /**
     * @return array<int, CategoryOptionData>
     */
    public function __invoke(): array
    {
        $tree = ($this->getCategoryTree)(CategoryTreeAudience::Admin);

        $options = [];
        $flatten = function (array $nodes, array $ancestorNames) use (&$flatten, &$options): void {
            /** @var CategoryTreeNodeData $node */
            foreach ($nodes as $node) {
                $path = array_merge($ancestorNames, [$node->name]);

                if ($node->children === []) {
                    if ($node->is_effectively_active) {
                        $options[] = new CategoryOptionData(
                            public_id: $node->public_id,
                            name: $node->name,
                            label: implode(' > ', $path),
                        );
                    }
                } else {
                    $flatten($node->children, $path);
                }
            }
        };

        $flatten($tree, []);

        return $options;
    }
}
