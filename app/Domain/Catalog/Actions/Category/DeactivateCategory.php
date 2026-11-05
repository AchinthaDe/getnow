<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Category;

use App\Domain\Catalog\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class DeactivateCategory
{
    /**
     * @throws ModelNotFoundException
     */
    public function __invoke(string $publicId, ?User $causer = null): void
    {
        DB::transaction(function () use ($publicId, $causer): void {
            /** @var Category $category */
            $category = Category::where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($category->is_active === false) {
                return;
            }

            $properties = [
                'old_is_active' => $category->is_active,
                'new_is_active' => false,
            ];

            $category->is_active = false;
            $category->updated_by = $causer?->id;
            $category->save();

            activity('catalog')
                ->performedOn($category)
                ->causedBy($causer)
                ->withProperties(array_merge(['public_id' => $category->public_id], $properties))
                ->event('category.deactivated')
                ->log('category.deactivated');
        });
    }
}
