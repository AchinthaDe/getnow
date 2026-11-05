<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Category;

use App\Domain\Catalog\Data\Category\UpdateCategoryData;
use App\Domain\Catalog\Exceptions\CategoryRuleViolation;
use App\Domain\Catalog\Models\Category;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class UpdateCategory
{
    public function __invoke(Category|string $category, UpdateCategoryData $data, ?User $causer = null): Category
    {
        return DB::transaction(function () use ($category, $data, $causer): Category {
            $publicId = $category instanceof Category ? $category->public_id : $category;

            /** @var Category $categoryModel */
            $categoryModel = Category::where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            $properties = [];
            $changed = [];

            if ($data->name !== null && $data->name !== $categoryModel->name) {
                $siblingQuery = Category::where('id', '!=', $categoryModel->id)
                    ->whereRaw('lower(name) = lower(?)', [$data->name]);

                if ($categoryModel->parent_id === null) {
                    $siblingQuery->whereNull('parent_id');
                } else {
                    $siblingQuery->where('parent_id', $categoryModel->parent_id);
                }

                if ($siblingQuery->exists()) {
                    throw CategoryRuleViolation::siblingNameClash($data->name, $categoryModel->parent?->public_id);
                }

                $properties['old_name'] = $categoryModel->name;
                $properties['new_name'] = $data->name;
                $categoryModel->name = $data->name;
                $changed[] = 'name';
            }

            if ($data->show_in_nav !== null && $data->show_in_nav !== $categoryModel->show_in_nav) {
                $properties['old_show_in_nav'] = $categoryModel->show_in_nav;
                $properties['new_show_in_nav'] = $data->show_in_nav;
                $categoryModel->show_in_nav = $data->show_in_nav;
                $changed[] = 'show_in_nav';
            }

            if ($data->sort_order !== null && $data->sort_order !== $categoryModel->sort_order) {
                $properties['old_sort_order'] = $categoryModel->sort_order;
                $properties['new_sort_order'] = $data->sort_order;
                $categoryModel->sort_order = $data->sort_order;
                $changed[] = 'sort_order';
            }

            if ($changed === []) {
                return $categoryModel;
            }

            $categoryModel->updated_by = $causer?->id;

            try {
                $categoryModel->save();
            } catch (UniqueConstraintViolationException) {
                throw CategoryRuleViolation::siblingNameClash($categoryModel->name, $categoryModel->parent?->public_id);
            }

            activity('catalog')
                ->performedOn($categoryModel)
                ->causedBy($causer)
                ->withProperties(array_merge(['public_id' => $categoryModel->public_id], $properties))
                ->event('category.updated')
                ->log('category.updated');

            return $categoryModel;
        });
    }
}
