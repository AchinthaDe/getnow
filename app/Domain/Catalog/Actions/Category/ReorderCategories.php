<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Category;

use App\Domain\Catalog\Data\Category\ReorderCategoriesData;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Support\CategorySiblingLock;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ReorderCategories
{
    /**
     * @throws CategoryRuleViolation
     */
    public function __invoke(ReorderCategoriesData $data, ?User $causer = null): void
    {
        DB::transaction(function () use ($data, $causer): void {
            $parent = null;
            $parentId = null;
            $parentPublicId = null;

            if ($data->parent_public_id !== null) {
                /** @var Category|null $parent */
                $parent = Category::where('public_id', $data->parent_public_id)->first();

                if ($parent === null) {
                    throw CategoryRuleViolation::parentNotFound($data->parent_public_id);
                }

                $parentId = $parent->id;
                $parentPublicId = $parent->public_id;
                CategorySiblingLock::acquire($parentId);
            } else {
                CategorySiblingLock::acquire(null);
            }

            /** @var Collection<int, Category> $existing */
            $existing = Category::query()
                ->when($parentId === null, fn ($q) => $q->whereNull('parent_id'), fn ($q) => $q->where('parent_id', $parentId))
                ->orderBy('sort_order', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // Validate that submitted array is a strict permutation of existing siblings
            $submitted = $data->ordered_public_ids;
            if (count($submitted) !== count(array_unique($submitted)) || count($submitted) !== $existing->count()) {
                throw CategoryRuleViolation::invalidSiblingOrder($parentPublicId);
            }

            $existingPublicIds = $existing->pluck('public_id')->all();
            if (array_diff($submitted, $existingPublicIds) !== [] || array_diff($existingPublicIds, $submitted) !== []) {
                throw CategoryRuleViolation::invalidSiblingOrder($parentPublicId);
            }

            $orderIndexMap = array_flip($submitted);
            $hasChanges = false;

            foreach ($existing as $sibling) {
                $targetSortOrder = $orderIndexMap[$sibling->public_id];
                if ($sibling->sort_order !== $targetSortOrder) {
                    $sibling->sort_order = $targetSortOrder;
                    $sibling->updated_by = $causer?->id;
                    $sibling->save();
                    $hasChanges = true;
                }
            }

            if (! $hasChanges) {
                return;
            }

            $logger = activity('catalog');
            if ($parent !== null) {
                $logger->performedOn($parent);
            }
            $logger->causedBy($causer)
                ->withProperties([
                    'parent_public_id' => $parentPublicId,
                    'old_ordered_public_ids' => $existingPublicIds,
                    'new_ordered_public_ids' => $submitted,
                ])
                ->event('category.reordered')
                ->log('category.reordered');
        });
    }
}
