<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Brand;

use App\Domain\Catalog\Data\Brand\CreateBrandData;
use App\Domain\Catalog\Exceptions\BrandRuleViolation;
use App\Domain\Catalog\Models\Brand;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateBrand
{
    public function __invoke(CreateBrandData $data, ?User $causer = null): Brand
    {
        return DB::transaction(function () use ($data, $causer): Brand {
            if (Brand::whereRaw('lower(name) = lower(?)', [$data->name])->exists()) {
                throw BrandRuleViolation::duplicateName($data->name);
            }

            $baseSlug = Str::slug($data->name);
            if ($baseSlug === '') {
                throw BrandRuleViolation::emptySlug($data->name);
            }

            $slug = $baseSlug;
            $suffix = 2;
            while (Brand::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $brand = new Brand;
            $brand->name = $data->name;
            $brand->slug = $slug;
            $brand->show_in_nav = $data->show_in_nav;
            $brand->is_active = true;
            $brand->created_by = $causer?->id;
            $brand->updated_by = $causer?->id;

            try {
                $brand->save();
            } catch (UniqueConstraintViolationException) {
                throw BrandRuleViolation::duplicateName($data->name);
            }

            activity('catalog')
                ->performedOn($brand)
                ->causedBy($causer)
                ->withProperties([
                    'public_id' => $brand->public_id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'show_in_nav' => $brand->show_in_nav,
                ])
                ->event('brand.created')
                ->log('brand.created');

            return $brand;
        });
    }
}
