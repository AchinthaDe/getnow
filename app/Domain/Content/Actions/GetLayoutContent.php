<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Catalog\Actions\Category\GetCategoryTree;
use App\Domain\Catalog\Data\Category\CategoryTreeNodeData;
use App\Domain\Catalog\Enums\CategoryTreeAudience;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\LayoutBlockData;
use App\Domain\Content\Data\LayoutData;
use App\Domain\Content\Data\LayoutMenuData;
use App\Domain\Content\Data\LayoutMetaData;
use App\Domain\Content\Data\LayoutResponseData;
use App\Domain\Content\Data\LayoutSettingsData;
use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Services\ResolveServablePayload;
use App\Domain\Content\Settings\StorefrontSettings;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GetLayoutContent
{
    public function __construct(
        private readonly StorefrontSettings $settings,
        private readonly BlockRegistry $registry,
        private readonly ResolveServablePayload $resolveServablePayload,
        private readonly GetCategoryTree $getCategoryTree,
        private readonly ProjectMenuLayoutItems $projectMenuLayoutItems,
    ) {}

    public function __invoke(): LayoutResponseData
    {
        $settingsData = new LayoutSettingsData(
            site_name: $this->settings->site_name,
            default_meta_description: $this->settings->default_meta_description,
            search_placeholder: $this->settings->search_placeholder,
            footer_brand_text: $this->settings->footer_brand_text,
        );

        // Pre-index blocks by placement to bound queries regardless of placement count.
        $allBlocks = ContentBlock::visible()->servingOrder()->get();

        $blocksByPlacement = [];
        foreach ($allBlocks as $block) {
            if ($block->placement instanceof Placement) {
                $blocksByPlacement[$block->placement->value][] = $block;
            }
        }

        $placementsData = [];
        foreach (Placement::cases() as $placement) {
            $candidates = $blocksByPlacement[$placement->value] ?? [];
            $selectedBlocks = [];
            $maxCount = $placement->maxCount();
            $allowedTypes = $placement->allowedBlockTypes();

            foreach ($candidates as $candidate) {
                // Pass candidates to ResolveServablePayload first to log corruption and unknown types
                $health = ($this->resolveServablePayload)($candidate, log: true);

                if (! $health->isServable() || $health->payload === null) {
                    continue;
                }

                // Check placement allowed type second without logging
                if (! in_array($candidate->type, $allowedTypes, true)) {
                    continue;
                }

                $definition = $this->registry->get($candidate->type);
                if ($definition === null) {
                    continue;
                }

                $selectedBlocks[] = new LayoutBlockData(
                    id: $candidate->public_id,
                    type: $candidate->type,
                    version: $definition->schemaVersion(),
                    data: $health->payload->toArray(),
                );

                if (count($selectedBlocks) >= $maxCount) {
                    break;
                }
            }

            $placementsData[$placement->value] = $selectedBlocks;
        }

        $menus = Menu::with([
            'rootItems' => fn (HasMany $q) => $q->orderBy('sort_order')->orderBy('id'),
            'rootItems.children' => fn (HasMany $q) => $q->orderBy('sort_order')->orderBy('id'),
        ])
            ->get()
            ->keyBy(fn (Menu $m): string => $m->key->value);

        $hasServableTypedItem = false;
        foreach ($menus as $menu) {
            foreach ($menu->rootItems as $rootItem) {
                if (! $rootItem->is_enabled) {
                    continue;
                }

                if ($rootItem->kind === MenuItemKind::CATEGORY || $rootItem->kind === MenuItemKind::CATEGORY_TREE) {
                    $hasServableTypedItem = true;
                    break 2;
                }

                foreach ($rootItem->children as $childItem) {
                    if (! $childItem->is_enabled) {
                        continue;
                    }

                    if ($childItem->kind === MenuItemKind::CATEGORY || $childItem->kind === MenuItemKind::CATEGORY_TREE) {
                        $hasServableTypedItem = true;
                        break 2;
                    }
                }
            }
        }

        $categoryRoots = [];
        $categoriesByPublicId = [];
        if ($hasServableTypedItem) {
            $categoryRoots = ($this->getCategoryTree)(CategoryTreeAudience::Storefront);
            $categoriesByPublicId = $this->indexCategoriesByPublicId($categoryRoots);
        }

        $menusData = [];
        foreach (MenuKey::cases() as $menuKey) {
            $menu = $menus->get($menuKey->value);

            if ($menu === null) {
                $menusData[$menuKey->value] = new LayoutMenuData(
                    title: null,
                    items: [],
                );

                continue;
            }

            $items = ($this->projectMenuLayoutItems)(
                $menu->rootItems,
                $categoryRoots,
                $categoriesByPublicId,
            );

            $menusData[$menuKey->value] = new LayoutMenuData(
                title: $menu->title,
                items: $items,
            );
        }

        // Hash only the canonical data object so revision tokens remain stable across request timestamps.
        $layoutData = new LayoutData(
            settings: $settingsData,
            placements: $placementsData,
            menus: $menusData,
        );

        $canonicalJson = json_encode(
            $layoutData->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $revision = hash('sha256', $canonicalJson);
        $generatedAt = now()->utc()->toIso8601ZuluString();

        $metaData = new LayoutMetaData(
            api_version: 1,
            revision: $revision,
            generated_at: $generatedAt,
        );

        return new LayoutResponseData(
            data: $layoutData,
            meta: $metaData,
        );
    }

    /**
     * @param  array<int, CategoryTreeNodeData>  $nodes
     * @return array<string, CategoryTreeNodeData>
     */
    private function indexCategoriesByPublicId(array $nodes): array
    {
        $map = [];
        foreach ($nodes as $node) {
            $map[$node->public_id] = $node;
            if (! empty($node->children)) {
                $map += $this->indexCategoriesByPublicId($node->children);
            }
        }

        return $map;
    }
}
