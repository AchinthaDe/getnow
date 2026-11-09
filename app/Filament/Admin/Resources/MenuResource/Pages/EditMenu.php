<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\MenuResource\Pages;

use App\Domain\Catalog\Actions\Category\GetCategoryTree;
use App\Domain\Catalog\Data\Category\CategoryTreeNodeData;
use App\Domain\Catalog\Enums\CategoryTreeAudience;
use App\Domain\Content\Actions\GetMenuTree;
use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Data\MenuItemData;
use App\Domain\Content\Data\UpdateMenuData;
use App\Domain\Content\Data\UpdateMenuItemInputData;
use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Exceptions\InvalidMenuTreeException;
use App\Domain\Content\Models\Menu;
use App\Filament\Admin\Resources\MenuResource;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

final class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;

    /** @var array<int, CategoryTreeNodeData>|null */
    protected ?array $categoryTree = null;

    /** @var array<string, string>|null */
    protected ?array $categoryOptions = null;

    /** @var array<string, CategoryTreeNodeData>|null */
    protected ?array $categoriesByPublicId = null;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<int, CategoryTreeNodeData>
     */
    public function getCategoryTree(): array
    {
        if ($this->categoryTree !== null) {
            return $this->categoryTree;
        }

        /** @var GetCategoryTree $getTree */
        $getTree = app(GetCategoryTree::class);

        return $this->categoryTree = $getTree(CategoryTreeAudience::Admin);
    }

    /**
     * @return array<string, string>
     */
    public function getCategoryOptions(): array
    {
        if ($this->categoryOptions !== null) {
            return $this->categoryOptions;
        }

        $options = [];
        $traverse = function (array $nodes, int $depth) use (&$traverse, &$options): void {
            foreach ($nodes as $node) {
                $prefix = $depth > 0 ? str_repeat('— ', $depth) : '';
                $options[$node->public_id] = $prefix.$node->name;
                if (! empty($node->children)) {
                    $traverse($node->children, $depth + 1);
                }
            }
        };

        $traverse($this->getCategoryTree(), 0);

        return $this->categoryOptions = $options;
    }

    /**
     * @return array<string, CategoryTreeNodeData>
     */
    public function getCategoriesByPublicId(): array
    {
        if ($this->categoriesByPublicId !== null) {
            return $this->categoriesByPublicId;
        }

        $map = [];
        $traverse = function (array $nodes) use (&$traverse, &$map): void {
            foreach ($nodes as $node) {
                $map[$node->public_id] = $node;
                if (! empty($node->children)) {
                    $traverse($node->children);
                }
            }
        };

        $traverse($this->getCategoryTree());

        return $this->categoriesByPublicId = $map;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Menu $record */
        $record = $this->getRecord();
        $tree = app(GetMenuTree::class)($record);

        return [
            'title' => $tree->title,
            'items' => array_values(array_map(function (MenuItemData $item): array {
                return [
                    'public_id' => $item->public_id,
                    'kind' => $item->kind->value,
                    'category_public_id' => $item->category_public_id,
                    'label' => $item->label,
                    'url' => $item->url,
                    'opens_in_new_tab' => $item->opens_in_new_tab,
                    'is_enabled' => $item->is_enabled,
                    'children' => array_values(array_map(function (MenuItemData $child): array {
                        return [
                            'public_id' => $child->public_id,
                            'kind' => $child->kind->value,
                            'category_public_id' => $child->category_public_id,
                            'label' => $child->label,
                            'url' => $child->url,
                            'opens_in_new_tab' => $child->opens_in_new_tab,
                            'is_enabled' => $child->is_enabled,
                        ];
                    }, $item->children)),
                ];
            }, $tree->items)),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Menu) {
            throw new \LogicException('Expected Menu model instance.');
        }

        $tree = app(GetMenuTree::class)($record);
        /** @var array<string, MenuItemData> $existingItems */
        $existingItems = [];
        $indexItems = function (array $items) use (&$indexItems, &$existingItems): void {
            foreach ($items as $item) {
                $existingItems[$item->public_id] = $item;
                if (! empty($item->children)) {
                    $indexItems($item->children);
                }
            }
        };
        $indexItems($tree->items);

        $resolveKind = function (mixed $rawKind, ?MenuItemData $existing): MenuItemKind {
            if ($rawKind instanceof MenuItemKind) {
                return $rawKind;
            }
            if (is_string($rawKind)) {
                $tryKind = MenuItemKind::tryFrom($rawKind);
                if ($tryKind !== null) {
                    return $tryKind;
                }
            }
            if ($existing !== null) {
                return $existing->kind;
            }

            return MenuItemKind::LINK;
        };

        /** @var array<int|string, array<string, mixed>> $rawItems */
        $rawItems = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

        $normalizedItems = array_values(array_map(function (array $item) use ($resolveKind, $existingItems): UpdateMenuItemInputData {
            $publicId = isset($item['public_id']) && is_string($item['public_id']) ? $item['public_id'] : null;
            $existingItem = $publicId !== null ? ($existingItems[$publicId] ?? null) : null;
            $kind = $resolveKind($item['kind'] ?? null, $existingItem);

            $categoryPublicId = null;
            if ($kind !== MenuItemKind::LINK) {
                $rawCat = $item['category_public_id'] ?? null;
                if (is_string($rawCat)) {
                    $trimmed = trim($rawCat);
                    $categoryPublicId = $trimmed !== '' ? $trimmed : null;
                }
            }

            $url = $kind === MenuItemKind::LINK ? (string) ($item['url'] ?? '') : null;

            if ($kind === MenuItemKind::LINK) {
                $label = (string) ($item['label'] ?? '');
            } elseif ($kind === MenuItemKind::CATEGORY) {
                $rawLabel = $item['label'] ?? null;
                if (is_string($rawLabel)) {
                    $trimmed = trim($rawLabel);
                    $label = $trimmed !== '' ? $trimmed : null;
                } else {
                    $label = null;
                }
            } else {
                $rawLabel = $item['label'] ?? null;
                if (is_string($rawLabel) && trim($rawLabel) !== '') {
                    $label = trim($rawLabel);
                } else {
                    $label = $existingItem?->label;
                }
            }

            $opensInNewTab = $kind === MenuItemKind::CATEGORY_TREE
                ? ($existingItem !== null ? $existingItem->opens_in_new_tab : false)
                : (bool) ($item['opens_in_new_tab'] ?? false);

            $isEnabled = (bool) ($item['is_enabled'] ?? true);

            $children = [];
            if ($kind !== MenuItemKind::CATEGORY_TREE) {
                /** @var array<int|string, array<string, mixed>> $rawChildren */
                $rawChildren = isset($item['children']) && is_array($item['children']) ? $item['children'] : [];

                $children = array_values(array_map(function (array $child) use ($resolveKind, $existingItems): UpdateMenuItemInputData {
                    $childPublicId = isset($child['public_id']) && is_string($child['public_id']) ? $child['public_id'] : null;
                    $existingChild = $childPublicId !== null ? ($existingItems[$childPublicId] ?? null) : null;
                    $childKind = $resolveKind($child['kind'] ?? null, $existingChild);

                    $childCategoryPublicId = null;
                    if ($childKind !== MenuItemKind::LINK) {
                        $rawCat = $child['category_public_id'] ?? null;
                        if (is_string($rawCat)) {
                            $trimmed = trim($rawCat);
                            $childCategoryPublicId = $trimmed !== '' ? $trimmed : null;
                        }
                    }

                    $childUrl = $childKind === MenuItemKind::LINK ? (string) ($child['url'] ?? '') : null;

                    if ($childKind === MenuItemKind::LINK) {
                        $childLabel = (string) ($child['label'] ?? '');
                    } elseif ($childKind === MenuItemKind::CATEGORY) {
                        $rawLabel = $child['label'] ?? null;
                        if (is_string($rawLabel)) {
                            $trimmed = trim($rawLabel);
                            $childLabel = $trimmed !== '' ? $trimmed : null;
                        } else {
                            $childLabel = null;
                        }
                    } else {
                        $rawLabel = $child['label'] ?? null;
                        $childLabel = (is_string($rawLabel) && trim($rawLabel) !== '') ? trim($rawLabel) : null;
                    }

                    $childOpensInNewTab = (bool) ($child['opens_in_new_tab'] ?? false);
                    $childIsEnabled = (bool) ($child['is_enabled'] ?? true);

                    return new UpdateMenuItemInputData(
                        public_id: $childPublicId,
                        label: $childLabel,
                        url: $childUrl,
                        icon: null,
                        opens_in_new_tab: $childOpensInNewTab,
                        is_enabled: $childIsEnabled,
                        children: [],
                        kind: $childKind,
                        category_public_id: $childCategoryPublicId,
                    );
                }, $rawChildren));
            }

            return new UpdateMenuItemInputData(
                public_id: $publicId,
                label: $label,
                url: $url,
                icon: null,
                opens_in_new_tab: $opensInNewTab,
                is_enabled: $isEnabled,
                children: $children,
                kind: $kind,
                category_public_id: $categoryPublicId,
            );
        }, $rawItems));

        $title = isset($data['title']) && is_string($data['title']) ? $data['title'] : null;

        $updateData = new UpdateMenuData(
            title: $title,
            items: $normalizedItems,
        );

        try {
            app(UpdateMenu::class)($record, $updateData, Filament::auth()->user());
        } catch (InvalidMenuTreeException) {
            Notification::make()
                ->danger()
                ->title('Update Failed')
                ->body('The menu structure is invalid and could not be saved.')
                ->send();

            throw new Halt;
        }

        return $record;
    }

    protected function afterSave(): void
    {
        $this->fillForm();
    }
}
