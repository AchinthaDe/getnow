<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Data\Brand;

use App\Domain\Catalog\Models\Brand;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

final class BrandData extends Data
{
    public function __construct(
        public string $public_id,
        public string $name,
        public string $slug,
        public bool $is_active,
        public bool $show_in_nav,
        public Carbon|CarbonImmutable|CarbonInterface|null $created_at = null,
        public Carbon|CarbonImmutable|CarbonInterface|null $updated_at = null,
    ) {}

    public static function fromModel(Brand $brand): self
    {
        return new self(
            public_id: $brand->public_id,
            name: $brand->name,
            slug: $brand->slug,
            is_active: $brand->is_active,
            show_in_nav: $brand->show_in_nav,
            created_at: $brand->created_at,
            updated_at: $brand->updated_at,
        );
    }
}
