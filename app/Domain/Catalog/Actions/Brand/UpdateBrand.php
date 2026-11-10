<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Brand;

use App\Domain\Catalog\Data\Brand\UpdateBrandData;
use App\Domain\Catalog\Exceptions\BrandRuleViolation;
use App\Domain\Catalog\Models\Brand;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class UpdateBrand
{
    public function __invoke(Brand|string $brand, UpdateBrandData $data, ?User $causer = null): Brand
    {
        return DB::transaction(function () use ($brand, $data, $causer): Brand {
            $publicId = $brand instanceof Brand ? $brand->public_id : $brand;

            /** @var Brand $brandModel */
            $brandModel = Brand::where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            $properties = [];
            $changed = [];

            if ($data->name !== null && $data->name !== $brandModel->name) {
                if (Brand::where('id', '!=', $brandModel->id)->whereRaw('lower(name) = lower(?)', [$data->name])->exists()) {
                    throw BrandRuleViolation::duplicateName($data->name);
                }

                $properties['old_name'] = $brandModel->name;
                $properties['new_name'] = $data->name;
                $brandModel->name = $data->name;
                $changed[] = 'name';
            }

            if ($data->show_in_nav !== null && $data->show_in_nav !== $brandModel->show_in_nav) {
                $properties['old_show_in_nav'] = $brandModel->show_in_nav;
                $properties['new_show_in_nav'] = $data->show_in_nav;
                $brandModel->show_in_nav = $data->show_in_nav;
                $changed[] = 'show_in_nav';
            }

            if ($changed === []) {
                return $brandModel;
            }

            $brandModel->updated_by = $causer?->id;

            try {
                $brandModel->save();
            } catch (UniqueConstraintViolationException) {
                throw BrandRuleViolation::duplicateName($brandModel->name);
            }

            activity('catalog')
                ->performedOn($brandModel)
                ->causedBy($causer)
                ->withProperties(array_merge(['public_id' => $brandModel->public_id], $properties))
                ->event('brand.updated')
                ->log('brand.updated');

            return $brandModel;
        });
    }
}
