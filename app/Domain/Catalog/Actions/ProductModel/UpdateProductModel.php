<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\ProductModel;

use App\Domain\Catalog\Data\ProductModel\UpdateProductModelData;
use App\Domain\Catalog\Exceptions\ProductModelRuleViolation;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\ProductModel;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class UpdateProductModel
{
    /**
     * @throws ModelNotFoundException
     * @throws ProductModelRuleViolation
     */
    public function __invoke(ProductModel|string $model, UpdateProductModelData $data, ?User $causer = null): ProductModel
    {
        return DB::transaction(function () use ($model, $data, $causer): ProductModel {
            $publicId = $model instanceof ProductModel ? $model->public_id : $model;

            /** @var ProductModel $resolvedModel */
            $resolvedModel = ProductModel::where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($data->name === null || $data->name === $resolvedModel->name) {
                return $resolvedModel;
            }

            if (ProductModel::where('brand_id', $resolvedModel->brand_id)
                ->where('id', '!=', $resolvedModel->id)
                ->whereRaw('lower(name) = lower(?)', [$data->name])
                ->exists()) {
                /** @var string $brandPublicId */
                $brandPublicId = Brand::whereKey($resolvedModel->brand_id)->value('public_id') ?? '';
                throw ProductModelRuleViolation::duplicateName($data->name, $brandPublicId);
            }

            $oldName = $resolvedModel->name;
            $resolvedModel->name = $data->name;
            $resolvedModel->updated_by = $causer?->id;

            try {
                $resolvedModel->save();
            } catch (UniqueConstraintViolationException) {
                /** @var string $brandPublicId */
                $brandPublicId = Brand::whereKey($resolvedModel->brand_id)->value('public_id') ?? '';
                throw ProductModelRuleViolation::duplicateName($data->name, $brandPublicId);
            }

            activity('catalog')
                ->performedOn($resolvedModel)
                ->causedBy($causer)
                ->withProperties([
                    'public_id' => $resolvedModel->public_id,
                    'old_name' => $oldName,
                    'new_name' => $resolvedModel->name,
                ])
                ->event('product_model.updated')
                ->log('product_model.updated');

            return $resolvedModel;
        });
    }
}
