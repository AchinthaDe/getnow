<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Category;

use App\Domain\Catalog\Data\Category\MoveCategoryData;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\ProductModel;
use App\Domain\Catalog\Support\CategorySiblingLock;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class MoveCategory
{
    /**
     * @throws ModelNotFoundException
     * @throws CategoryRuleViolation
     */
    public function __invoke(string $publicId, MoveCategoryData $data, ?User $causer = null): void
    {
        DB::transaction(function () use ($publicId, $data, $causer): void {
            $newParent = null;
            $newParentId = null;
            $newParentPublicId = null;

            if ($data->parent_public_id !== null) {
                /** @var Category $newParent */
                $newParent = Category::where('public_id', $data->parent_public_id)->firstOrFail();
                $newParentId = $newParent->id;
                $newParentPublicId = $newParent->public_id;
                CategorySiblingLock::acquire($newParentId);

                if (ProductModel::where('category_id', $newParentId)->exists()) {
                    throw CategoryRuleViolation::newParentHasModels($newParentPublicId);
                }
            } else {
                CategorySiblingLock::acquire(null);
            }

            /** @var Category $category */
            $category = Category::where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($category->parent_id === $newParentId) {
                return;
            }

            // Rule 1: Cycle check
            if ($newParentId !== null) {
                if ($newParentId === $category->id) {
                    throw CategoryRuleViolation::cycleDetected($category->public_id, $newParentPublicId);
                }

                $cycleRow = DB::selectOne(
                    'WITH RECURSIVE anc AS (
                        SELECT id, parent_id FROM categories WHERE id = ?
                        UNION
                        SELECT p.id, p.parent_id FROM categories p JOIN anc ON p.id = anc.parent_id
                    )
                    SELECT 1 FROM anc WHERE id = ? LIMIT 1',
                    [$newParentId, $category->id]
                );

                if ($cycleRow !== null) {
                    throw CategoryRuleViolation::cycleDetected($category->public_id, $newParentPublicId);
                }
            }

            // Rule 2: Subtree depth cap
            $newParentDepth = 0;
            if ($newParentId !== null) {
                $depthRow = DB::selectOne(
                    'WITH RECURSIVE parent_chain AS (
                        SELECT id, parent_id FROM categories WHERE id = ?
                        UNION
                        SELECT p.id, p.parent_id FROM categories p JOIN parent_chain ON p.id = parent_chain.parent_id
                    )
                    SELECT COUNT(*) AS depth FROM parent_chain',
                    [$newParentId]
                );
                $newParentDepth = (int) ($depthRow->depth ?? 0);
            }

            $heightRow = DB::selectOne(
                'WITH RECURSIVE subtree AS (
                    SELECT id, 1 AS height FROM categories WHERE id = ?
                    UNION ALL
                    SELECT c.id, s.height + 1 FROM categories c JOIN subtree s ON c.parent_id = s.id
                )
                SELECT COALESCE(MAX(height), 1) AS height FROM subtree',
                [$category->id]
            );
            $subtreeHeight = (int) ($heightRow->height ?? 1);

            if (($newParentDepth + $subtreeHeight) > Category::MAX_DEPTH) {
                throw CategoryRuleViolation::maxDepthExceeded(Category::MAX_DEPTH);
            }

            // Rule 3: Sibling name clash
            $siblingQuery = Category::where('id', '!=', $category->id)
                ->whereRaw('lower(name) = lower(?)', [$category->name]);

            if ($newParentId === null) {
                $siblingQuery->whereNull('parent_id');
            } else {
                $siblingQuery->where('parent_id', $newParentId);
            }

            if ($siblingQuery->exists()) {
                throw CategoryRuleViolation::siblingNameClash($category->name, $newParentPublicId);
            }

            // Calculate next sort order under new parent
            $maxSortOrder = Category::where('id', '!=', $category->id)
                ->when($newParentId === null, fn ($q) => $q->whereNull('parent_id'), fn ($q) => $q->where('parent_id', $newParentId))
                ->max('sort_order');
            $newSortOrder = $maxSortOrder !== null ? ((int) $maxSortOrder) + 1 : 0;

            $oldParentPublicId = $category->parent_id !== null
                ? Category::whereKey($category->parent_id)->value('public_id')
                : null;
            $oldSortOrder = $category->sort_order;

            $category->parent_id = $newParentId;
            $category->sort_order = $newSortOrder;
            $category->updated_by = $causer?->id;

            try {
                $category->save();
            } catch (UniqueConstraintViolationException) {
                throw CategoryRuleViolation::siblingNameClash($category->name, $newParentPublicId);
            }

            activity('catalog')
                ->performedOn($category)
                ->causedBy($causer)
                ->withProperties([
                    'public_id' => $category->public_id,
                    'old_parent_public_id' => $oldParentPublicId,
                    'new_parent_public_id' => $newParentPublicId,
                    'old_sort_order' => $oldSortOrder,
                    'new_sort_order' => $newSortOrder,
                ])
                ->event('category.moved')
                ->log('category.moved');
        });
    }
}
