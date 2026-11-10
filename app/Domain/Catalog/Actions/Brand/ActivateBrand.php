<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Brand;

use App\Domain\Catalog\Models\Brand;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class ActivateBrand
{
    /**
     * @throws ModelNotFoundException
     */
    public function __invoke(string $publicId, ?User $causer = null): void
    {
        DB::transaction(function () use ($publicId, $causer): void {
            /** @var Brand $brand */
            $brand = Brand::where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($brand->is_active === true) {
                return;
            }

            $properties = [
                'old_is_active' => false,
                'new_is_active' => true,
            ];

            $brand->is_active = true;
            $brand->updated_by = $causer?->id;
            $brand->save();

            activity('catalog')
                ->performedOn($brand)
                ->causedBy($causer)
                ->withProperties(array_merge(['public_id' => $brand->public_id], $properties))
                ->event('brand.activated')
                ->log('brand.activated');
        });
    }
}
