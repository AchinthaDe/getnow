<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Category;

use App\Domain\Catalog\Data\Category\CreateCategoryData;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateCategory
{
    public function __invoke(CreateCategoryData $data, ?User $causer = null): Category
    {
        return DB::transaction(function () use ($data, $causer): Category {
            if ($data->parent_public_id !== null) {
                /** @var Category|null $parent */
                $parent = Category::where('public_id', $data->parent_public_id)
                    ->lockForUpdate()
                    ->first();

                if ($parent === null) {
                    throw CategoryRuleViolation::parentNotFound($data->parent_public_id);
                }

                $lockKey = (int) (crc32('category_parent:'.$parent->id) & 0x7FFFFFFF);
                DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

                if ($parent->parent_id !== null) {
                    throw CategoryRuleViolation::maxDepthExceeded(Category::MAX_DEPTH);
                }

                if (Category::where('parent_id', $parent->id)->whereRaw('lower(name) = lower(?)', [$data->name])->exists()) {
                    throw CategoryRuleViolation::siblingNameClash($data->name, $parent->public_id);
                }

                $maxSortOrder = Category::where('parent_id', $parent->id)->max('sort_order');
                $sortOrder = $maxSortOrder !== null ? ((int) $maxSortOrder) + 1 : 0;
                $parentId = $parent->id;
                $parentPublicId = $parent->public_id;
            } else {
                $lockKey = (int) (crc32('category_parent:0') & 0x7FFFFFFF);
                DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);

                if (Category::whereNull('parent_id')->whereRaw('lower(name) = lower(?)', [$data->name])->exists()) {
                    throw CategoryRuleViolation::siblingNameClash($data->name, null);
                }

                $maxSortOrder = Category::whereNull('parent_id')->max('sort_order');
                $sortOrder = $maxSortOrder !== null ? ((int) $maxSortOrder) + 1 : 0;
                $parentId = null;
                $parentPublicId = null;
            }

            $baseSlug = Str::slug($data->name);
            if ($baseSlug === '') {
                throw CategoryRuleViolation::emptySlug($data->name);
            }

            $slug = $baseSlug;
            $suffix = 2;
            while (Category::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $category = new Category;
            $category->parent_id = $parentId;
            $category->name = $data->name;
            $category->slug = $slug;
            $category->sort_order = $sortOrder;
            $category->show_in_nav = $data->show_in_nav;
            $category->is_active = true;
            $category->created_by = $causer?->id;
            $category->updated_by = $causer?->id;

            try {
                $category->save();
            } catch (UniqueConstraintViolationException) {
                throw CategoryRuleViolation::siblingNameClash($data->name, $parentPublicId);
            }

            activity('catalog')
                ->performedOn($category)
                ->causedBy($causer)
                ->withProperties([
                    'public_id' => $category->public_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'parent_public_id' => $parentPublicId,
                ])
                ->event('category.created')
                ->log('category.created');

            return $category;
        });
    }
}
