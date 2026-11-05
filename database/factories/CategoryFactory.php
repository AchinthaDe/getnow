<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'public_id' => Str::lower((string) Str::ulid()),
            'parent_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'sort_order' => 0,
            'show_in_nav' => true,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function root(): self
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => null,
        ]);
    }

    public function child(Category $parent): self
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }

    public function hiddenFromNav(): self
    {
        return $this->state(fn (array $attributes) => [
            'show_in_nav' => false,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
