<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\ProductModel;

use App\Domain\Catalog\Data\ProductModel\ProductModelOptionData;
use App\Domain\Catalog\Models\ProductModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ListModelOptions
{
    public const int DEFAULT_LIMIT = 20;

    public const int MAX_LIMIT = 50;

    /**
     * @return array<int, ProductModelOptionData>
     */
    public function __invoke(string $brandPublicId, string $categoryPublicId, ?string $search = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $effectiveLimit = max(1, min($limit, self::MAX_LIMIT));

        $query = ProductModel::query()
            ->where('product_models.is_active', true)
            ->whereHas('brand', function (Builder $q) use ($brandPublicId): void {
                $q->where('public_id', $brandPublicId)->where('is_active', true);
            })
            ->whereHas('category', function (Builder $q) use ($categoryPublicId): void {
                $q->where('public_id', $categoryPublicId)->effectivelyActive();
            });

        if ($search !== null && trim($search) !== '') {
            $escaped = addcslashes(trim($search), '%_\\');
            $query->whereRaw('lower(name) LIKE lower(?) ESCAPE \'\\\'', ["%{$escaped}%"]);
        }

        $query->orderByRaw('lower(name) ASC')->orderBy('id', 'asc');
        $query->limit($effectiveLimit);

        /** @var Collection<int, ProductModel> $records */
        $records = $query->get(['public_id', 'name']);

        return $records->map(
            fn (ProductModel $model): ProductModelOptionData => new ProductModelOptionData(
                public_id: $model->public_id,
                name: $model->name,
            )
        )->values()->all();
    }
}
