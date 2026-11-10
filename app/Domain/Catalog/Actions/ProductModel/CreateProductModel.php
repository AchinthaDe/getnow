<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\ProductModel;

use App\Domain\Catalog\Data\ProductModel\CreateProductModelData;
use App\Domain\Catalog\Exceptions\ProductModelRuleViolation;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\ProductModel;
use App\Domain\Catalog\Support\CategorySiblingLock;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateProductModel
{
    public function __invoke(CreateProductModelData $data, ?User $causer = null): ProductModel
    {
        return DB::transaction(function () use ($data, $causer): ProductModel {
            /** @var Brand|null $brand */
            $brand = Brand::where('public_id', $data->brand_public_id)->first();
            if ($brand === null) {
                throw ProductModelRuleViolation::brandNotFound($data->brand_public_id);
            }

            if (! $brand->is_active) {
                throw ProductModelRuleViolation::brandInactive($brand->public_id);
            }

            /** @var Category|null $category */
            $category = Category::where('public_id', $data->category_public_id)->first();
            if ($category === null) {
                throw ProductModelRuleViolation::categoryNotFound($data->category_public_id);
            }

            CategorySiblingLock::acquire($category->id);

            if (Category::where('parent_id', $category->id)->exists()) {
                throw ProductModelRuleViolation::categoryNotLeaf($category->public_id);
            }

            if (! Category::whereKey($category->id)->effectivelyActive()->exists()) {
                throw ProductModelRuleViolation::categoryNotEffectivelyActive($category->public_id);
            }

            if (ProductModel::where('brand_id', $brand->id)->whereRaw('lower(name) = lower(?)', [$data->name])->exists()) {
                throw ProductModelRuleViolation::duplicateName($data->name, $brand->public_id);
            }

            $baseSlug = Str::slug($data->name);
            if ($baseSlug === '') {
                throw ProductModelRuleViolation::emptySlug($data->name);
            }

            $baseSlug = rtrim(substr($baseSlug, 0, 100), '-');
            if ($baseSlug === '') {
                throw ProductModelRuleViolation::emptySlug($data->name);
            }

            $slug = $baseSlug;
            $suffix = 2;
            while (ProductModel::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $model = new ProductModel;
            $model->brand_id = $brand->id;
            $model->category_id = $category->id;
            $model->name = $data->name;
            $model->slug = $slug;
            $model->is_active = true;
            $model->created_by = $causer?->id;
            $model->updated_by = $causer?->id;

            try {
                $model->save();
            } catch (UniqueConstraintViolationException) {
                throw ProductModelRuleViolation::duplicateName($data->name, $brand->public_id);
            }

            activity('catalog')
                ->performedOn($model)
                ->causedBy($causer)
                ->withProperties([
                    'public_id' => $model->public_id,
                    'brand_public_id' => $brand->public_id,
                    'category_public_id' => $category->public_id,
                    'name' => $model->name,
                    'slug' => $model->slug,
                ])
                ->event('product_model.created')
                ->log('product_model.created');

            return $model;
        });
    }
}
