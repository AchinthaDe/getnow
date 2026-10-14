<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\LayoutBlockData;
use App\Domain\Content\Data\LayoutData;
use App\Domain\Content\Data\LayoutMenuData;
use App\Domain\Content\Data\LayoutMenuItemData;
use App\Domain\Content\Data\LayoutMetaData;
use App\Domain\Content\Data\LayoutResponseData;
use App\Domain\Content\Data\LayoutSettingsData;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Services\ResolveServablePayload;
use App\Domain\Content\Settings\StorefrontSettings;

final class GetLayoutContent
{
    public function __construct(
        private readonly StorefrontSettings $settings,
        private readonly BlockRegistry $registry,
        private readonly ResolveServablePayload $resolveServablePayload,
    ) {}

    public function __invoke(): LayoutResponseData
    {
        // 1. Settings assembly
        $settingsData = new LayoutSettingsData(
            site_name: $this->settings->site_name,
            default_meta_description: $this->settings->default_meta_description,
            search_placeholder: $this->settings->search_placeholder,
            footer_brand_text: $this->settings->footer_brand_text,
        );

        // 2. Placements assembly (single query with index prefix status, is_enabled, sort_order)
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

        // 3. Menus assembly (2 queries: menus, then items ordered by sort_order, id)
        $menus = Menu::with(['items' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->get()
            ->keyBy(fn (Menu $m): string => $m->key->value);

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

            $rootItems = $menu->items
                ->filter(fn ($item) => $item->parent_id === null && $item->is_enabled === true);

            $childrenByParent = $menu->items
                ->filter(fn ($item) => $item->parent_id !== null && $item->is_enabled === true)
                ->groupBy('parent_id');

            $mappedRoots = [];
            foreach ($rootItems as $root) {
                $childItems = $childrenByParent->get($root->id, collect());
                $mappedChildren = [];

                foreach ($childItems as $child) {
                    $mappedChildren[] = new LayoutMenuItemData(
                        id: $child->public_id,
                        label: $child->label,
                        url: $child->url,
                        icon: $child->icon,
                        new_tab: (bool) $child->opens_in_new_tab,
                        children: [],
                    );
                }

                $mappedRoots[] = new LayoutMenuItemData(
                    id: $root->public_id,
                    label: $root->label,
                    url: $root->url,
                    icon: $root->icon,
                    new_tab: (bool) $root->opens_in_new_tab,
                    children: $mappedChildren,
                );
            }

            $menusData[$menuKey->value] = new LayoutMenuData(
                title: $menu->title,
                items: $mappedRoots,
            );
        }

        // 4. Data wrapping & deterministic revision hash generation
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
}
