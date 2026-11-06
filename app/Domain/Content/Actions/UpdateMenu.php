<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Catalog\Actions\Category\GetExistingCategories;
use App\Domain\Content\Data\MenuTreeData;
use App\Domain\Content\Data\UpdateMenuData;
use App\Domain\Content\Data\UpdateMenuItemInputData;
use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Exceptions\InvalidMenuTreeException;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Models\User;
use App\Support\Rules\SafeLink;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class UpdateMenu
{
    public const int MAX_TOP_LEVEL_ITEMS = 10;

    public const int MAX_CHILDREN_PER_PARENT = 10;

    public const int MAX_LABEL_LENGTH = 80;

    public const int MAX_URL_LENGTH = 2048;

    public const int MAX_TITLE_LENGTH = 255;

    public function __construct(
        private readonly GetExistingCategories $getExistingCategories = new GetExistingCategories,
    ) {}

    public function __invoke(Menu|MenuKey|string|int $menuIdentifier, UpdateMenuData $data, ?User $causer = null): MenuTreeData
    {
        try {
            return DB::transaction(function () use ($menuIdentifier, $data, $causer) {
                $menu = match (true) {
                    $menuIdentifier instanceof Menu => Menu::where('id', $menuIdentifier->id)->lockForUpdate()->firstOrFail(),
                    $menuIdentifier instanceof MenuKey => Menu::where('key', $menuIdentifier->value)->lockForUpdate()->firstOrFail(),
                    is_int($menuIdentifier) => Menu::where('id', $menuIdentifier)->lockForUpdate()->firstOrFail(),
                    is_string($menuIdentifier) => Menu::where('public_id', $menuIdentifier)
                        ->orWhere('key', $menuIdentifier)
                        ->lockForUpdate()
                        ->firstOrFail(),
                };

                /** @var Collection<string, MenuItem> $existingItems */
                $existingItems = $menu->items()->lockForUpdate()->get()->keyBy('public_id');

                $beforeTree = (new GetMenuTree)($menu);

                // Validate title
                $title = ($data->title !== null && trim($data->title) !== '') ? trim($data->title) : null;
                if ($title !== null && mb_strlen($title) > self::MAX_TITLE_LENGTH) {
                    throw InvalidMenuTreeException::titleTooLong(self::MAX_TITLE_LENGTH);
                }

                // Validate tree structure and inputs
                if (count($data->items) > self::MAX_TOP_LEVEL_ITEMS) {
                    throw InvalidMenuTreeException::capsExceeded('Maximum of '.self::MAX_TOP_LEVEL_ITEMS.' top-level items allowed.');
                }

                $menuKey = $menu->key;
                $allowedKinds = $menuKey->allowedKinds();

                $seenPublicIds = [];
                /** @var list<string> $referencedCategoryIds */
                $referencedCategoryIds = [];
                $safeLink = new SafeLink;

                $validateItem = function (UpdateMenuItemInputData $item, bool $isTopLevel) use (
                    &$seenPublicIds,
                    &$referencedCategoryIds,
                    $safeLink,
                    $existingItems,
                    $allowedKinds,
                    $menuKey,
                    &$validateItem
                ): void {
                    if (! in_array($item->kind, $allowedKinds, true)) {
                        throw InvalidMenuTreeException::disallowedKind($item->kind, $menuKey);
                    }

                    if ($item->icon !== null) {
                        throw InvalidMenuTreeException::iconNotSupported();
                    }

                    if ($item->public_id !== null) {
                        if (isset($seenPublicIds[$item->public_id])) {
                            throw InvalidMenuTreeException::duplicatePublicId($item->public_id);
                        }
                        if (! $existingItems->has($item->public_id)) {
                            throw InvalidMenuTreeException::unknownPublicId($item->public_id);
                        }
                        $seenPublicIds[$item->public_id] = true;
                    }

                    if ($item->kind === MenuItemKind::LINK) {
                        $trimmedLabel = trim((string) $item->label);
                        if ($trimmedLabel === '' || mb_strlen($trimmedLabel) > self::MAX_LABEL_LENGTH) {
                            throw InvalidMenuTreeException::labelInvalid('Label must be between 1 and '.self::MAX_LABEL_LENGTH.' characters.');
                        }

                        if ($item->url === null) {
                            throw InvalidMenuTreeException::urlRequiredForLink();
                        }

                        $validator = Validator::make(['url' => $item->url], ['url' => $safeLink]);
                        if ($validator->fails()) {
                            throw InvalidMenuTreeException::urlInvalid($item->url);
                        }

                        if ($item->category_public_id !== null) {
                            throw InvalidMenuTreeException::categoryReferenceNotAllowedForKind($item->kind);
                        }
                    } elseif ($item->kind === MenuItemKind::CATEGORY) {
                        if ($item->url !== null) {
                            throw InvalidMenuTreeException::urlNotAllowedForKind($item->kind);
                        }

                        if ($item->category_public_id === null || trim($item->category_public_id) === '') {
                            throw InvalidMenuTreeException::categoryReferenceRequired();
                        }

                        if ($item->label !== null) {
                            $trimmedLabel = trim($item->label);
                            if ($trimmedLabel !== '' && mb_strlen($trimmedLabel) > self::MAX_LABEL_LENGTH) {
                                throw InvalidMenuTreeException::labelInvalid('Label must be between 1 and '.self::MAX_LABEL_LENGTH.' characters.');
                            }
                        }

                        $referencedCategoryIds[] = trim($item->category_public_id);
                    } elseif ($item->kind === MenuItemKind::CATEGORY_TREE) {
                        if (! $isTopLevel) {
                            throw InvalidMenuTreeException::categoryTreeAtLevelOneOnly();
                        }

                        if (! empty($item->children)) {
                            throw InvalidMenuTreeException::categoryTreeCannotHaveChildren();
                        }

                        if ($item->url !== null) {
                            throw InvalidMenuTreeException::urlNotAllowedForKind($item->kind);
                        }

                        if ($item->label !== null) {
                            $trimmedLabel = trim($item->label);
                            if ($trimmedLabel !== '' && mb_strlen($trimmedLabel) > self::MAX_LABEL_LENGTH) {
                                throw InvalidMenuTreeException::labelInvalid('Label must be between 1 and '.self::MAX_LABEL_LENGTH.' characters.');
                            }
                        }

                        if ($item->category_public_id !== null && trim($item->category_public_id) !== '') {
                            $referencedCategoryIds[] = trim($item->category_public_id);
                        }
                    }

                    if ($isTopLevel) {
                        if (count($item->children) > self::MAX_CHILDREN_PER_PARENT) {
                            throw InvalidMenuTreeException::capsExceeded('Maximum of '.self::MAX_CHILDREN_PER_PARENT.' child items allowed per parent.');
                        }
                        foreach ($item->children as $child) {
                            $validateItem($child, false);
                        }
                    } else {
                        if (! empty($item->children)) {
                            throw InvalidMenuTreeException::depthExceeded();
                        }
                    }
                };

                foreach ($data->items as $item) {
                    $validateItem($item, true);
                }

                // Verify referenced categories exist via batched catalog query
                if ($referencedCategoryIds !== []) {
                    $existingCategories = ($this->getExistingCategories)($referencedCategoryIds);
                    $existingCategorySet = [];
                    foreach ($existingCategories as $catData) {
                        $existingCategorySet[$catData->public_id] = true;
                    }

                    foreach ($referencedCategoryIds as $refId) {
                        if (! isset($existingCategorySet[$refId])) {
                            throw InvalidMenuTreeException::categoryNotFound($refId);
                        }
                    }
                }

                // Update menu title and touch updated_at
                $menu->title = $title;
                $menu->touch();

                $buildAttributes = static function (UpdateMenuItemInputData $item): array {
                    $label = null;
                    if ($item->label !== null) {
                        $trimmed = trim($item->label);
                        $label = $trimmed !== '' ? $trimmed : null;
                    }

                    $url = $item->kind === MenuItemKind::LINK ? $item->url : null;

                    $categoryPublicId = null;
                    if ($item->kind !== MenuItemKind::LINK && $item->category_public_id !== null) {
                        $trimmedRef = trim($item->category_public_id);
                        $categoryPublicId = $trimmedRef !== '' ? $trimmedRef : null;
                    }

                    return [
                        'kind' => $item->kind,
                        'label' => $label,
                        'url' => $url,
                        'category_public_id' => $categoryPublicId,
                        'is_enabled' => $item->is_enabled,
                        'opens_in_new_tab' => $item->opens_in_new_tab,
                    ];
                };

                // Reordered Reconciliation Sequence
                $submittedPublicIds = [];
                $parentRecordIds = [];

                // Pass 1: Retained / New Top-Level Items (parent_id = null)
                foreach ($data->items as $i => $item) {
                    $attrs = array_merge($buildAttributes($item), [
                        'parent_id' => null,
                        'sort_order' => $i,
                    ]);

                    if ($item->public_id !== null && $existingItems->has($item->public_id)) {
                        $record = $existingItems->get($item->public_id);
                        $record->update($attrs);
                        $submittedPublicIds[] = $record->public_id;
                        $parentRecordIds[$i] = $record->id;
                    } else {
                        $newRecord = MenuItem::create(array_merge($attrs, [
                            'menu_id' => $menu->id,
                        ]));
                        $submittedPublicIds[] = $newRecord->public_id;
                        $parentRecordIds[$i] = $newRecord->id;
                    }
                }

                // Pass 2: Retained / New Child Items (parent_id = $parentRecordId)
                foreach ($data->items as $i => $item) {
                    $parentRecordId = $parentRecordIds[$i];
                    foreach ($item->children as $j => $child) {
                        $attrs = array_merge($buildAttributes($child), [
                            'parent_id' => $parentRecordId,
                            'sort_order' => $j,
                        ]);

                        if ($child->public_id !== null && $existingItems->has($child->public_id)) {
                            $record = $existingItems->get($child->public_id);
                            $record->update($attrs);
                            $submittedPublicIds[] = $record->public_id;
                        } else {
                            $newRecord = MenuItem::create(array_merge($attrs, [
                                'menu_id' => $menu->id,
                            ]));
                            $submittedPublicIds[] = $newRecord->public_id;
                        }
                    }
                }

                // Pass 3: Purge omitted items last (safely cascades without affecting promoted children)
                $menu->items()->whereNotIn('public_id', $submittedPublicIds)->delete();

                $savedTree = (new GetMenuTree)($menu);

                // Audit log
                activity('content')
                    ->performedOn($menu)
                    ->causedBy($causer)
                    ->withProperties([
                        'old_title' => $beforeTree->title,
                        'new_title' => $menu->title,
                        'old_items' => $beforeTree->toArray()['items'],
                        'new_items' => $savedTree->toArray()['items'],
                    ])
                    ->event('updated')
                    ->log('updated');

                return $savedTree;
            });
        } catch (\Throwable $e) {
            if ($e instanceof QueryException && in_array((string) $e->getCode(), ['23503', '23001'], true)) {
                if (str_contains((string) $e->getMessage(), 'menu_items_category_public_id_foreign')) {
                    throw InvalidMenuTreeException::categoryDeletedDuringSave();
                }
            }

            throw $e;
        }
    }
}
