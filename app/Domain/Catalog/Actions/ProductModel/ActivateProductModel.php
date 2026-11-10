<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\ProductModel;

use App\Domain\Catalog\Models\ProductModel;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class ActivateProductModel
{
    /**
     * @throws ModelNotFoundException
     */
    public function __invoke(string $publicId, ?User $causer = null): void
    {
        DB::transaction(function () use ($publicId, $causer): void {
            /** @var ProductModel $model */
            $model = ProductModel::where('public_id', $publicId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($model->is_active === true) {
                return;
            }

            $model->is_active = true;
            $model->updated_by = $causer?->id;
            $model->save();

            activity('catalog')
                ->performedOn($model)
                ->causedBy($causer)
                ->withProperties([
                    'public_id' => $model->public_id,
                    'old_is_active' => false,
                    'new_is_active' => true,
                ])
                ->event('product_model.activated')
                ->log('product_model.activated');
        });
    }
}
