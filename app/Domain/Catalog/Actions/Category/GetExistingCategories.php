<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions\Category;

use App\Domain\Catalog\Data\Category\CategoryExistenceData;
use App\Domain\Catalog\Models\Category;

final class GetExistingCategories
{
    /**
     * @param  array<int, string>  $publicIds
     * @return array<int, CategoryExistenceData>
     */
    public function __invoke(array $publicIds): array
    {
        if ($publicIds === []) {
            return [];
        }

        $uniqueIds = array_values(array_unique(array_filter($publicIds, fn (string $id): bool => trim($id) !== '')));

        if ($uniqueIds === []) {
            return [];
        }

        /** @var list<string> $existingIds */
        $existingIds = Category::query()
            ->whereIn('public_id', $uniqueIds)
            ->select('public_id')
            ->pluck('public_id')
            ->all();

        return array_map(
            fn (string $publicId): CategoryExistenceData => new CategoryExistenceData(public_id: $publicId),
            $existingIds
        );
    }
}
