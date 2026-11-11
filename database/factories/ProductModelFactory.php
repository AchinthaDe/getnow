<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\ProductModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductModel>
 */
final class ProductModelFactory extends Factory
{
    protected $model = ProductModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Model '.fake()->unique()->lexify('????');

        return [
            'public_id' => (string) Str::ulid(),
            'brand_id' => BrandFactory::new(),
            'category_id' => CategoryFactory::new(),
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forBrand(Brand $brand): self
    {
        return $this->state(fn (array $attributes) => [
            'brand_id' => $brand->id,
        ]);
    }

    public function forCategory(Category $category): self
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }
}
