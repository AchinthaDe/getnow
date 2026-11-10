<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Category;

use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\ProductModel;
use App\Domain\Catalog\Support\CategorySiblingLock;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class DeleteCategory
{
    /**
     * @throws ModelNotFoundException
     * @throws CategoryRuleViolation
     */
    public function __invoke(string $publicId, ?User $causer = null): void
    {
        DB::transaction(function () use ($publicId, $causer): void {
            // Step 1: Plain select to resolve internal ID without row lock
            /** @var Category $target */
            $target = Category::where('public_id', $publicId)->firstOrFail();
            $targetId = $target->id;

            // Step 2: Acquire advisory lock on children of this category
            CategorySiblingLock::acquire($targetId);

            // Step 3: Acquire pessimistic row lock on category to delete
            /** @var Category $category */
            $category = Category::where('id', $targetId)
                ->lockForUpdate()
                ->firstOrFail();

            // Step 4: Guard against existing child rows
            if (Category::where('parent_id', $category->id)->exists()) {
                throw CategoryRuleViolation::hasChildren($category->public_id);
            }

            if (ProductModel::where('category_id', $category->id)->exists()) {
                throw CategoryRuleViolation::inUse($category->public_id);
            }

            $oldParentPublicId = $category->parent_id !== null
                ? Category::whereKey($category->parent_id)->value('public_id')
                : null;

            $properties = [
                'public_id' => $category->public_id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_public_id' => $oldParentPublicId,
            ];

            activity('catalog')
                ->performedOn($category)
                ->causedBy($causer)
                ->withProperties($properties)
                ->event('category.deleted')
                ->log('category.deleted');

            try {
                $category->delete();
            } catch (\Throwable $e) {
                if ($e instanceof QueryException && in_array((string) $e->getCode(), ['23503', '23001'], true)) {
                    if (str_contains((string) $e->getMessage(), 'categories_parent_id_foreign')) {
                        throw CategoryRuleViolation::hasChildren($category->public_id);
                    }

                    throw CategoryRuleViolation::inUse($category->public_id);
                }

                throw $e;
            }
        });
    }
}
