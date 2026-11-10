<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Brand;

use App\Domain\Catalog\Data\Brand\BrandOptionData;
use App\Domain\Catalog\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

final class ListBrandOptions
{
    public const int DEFAULT_LIMIT = 20;

    public const int MAX_LIMIT = 50;

    /**
     * @return array<int, BrandOptionData>
     */
    public function __invoke(?string $search = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $effectiveLimit = max(1, min($limit, self::MAX_LIMIT));

        $query = Brand::query()->where('is_active', true);

        if ($search !== null && trim($search) !== '') {
            $escaped = addcslashes(trim($search), '%_\\');
            $query->whereRaw('lower(name) LIKE lower(?) ESCAPE \'\\\'', ["%{$escaped}%"]);
        }

        $query->orderByRaw('lower(name) ASC')->orderBy('id', 'asc');
        $query->limit($effectiveLimit);

        /** @var Collection<int, Brand> $records */
        $records = $query->get(['public_id', 'name']);

        return $records->map(
            fn (Brand $brand): BrandOptionData => new BrandOptionData(
                public_id: $brand->public_id,
                name: $brand->name,
            )
        )->values()->all();
    }
}
