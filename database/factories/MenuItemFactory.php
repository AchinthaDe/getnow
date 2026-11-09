<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuItem>
 */
final class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => Str::lower((string) Str::ulid()),
            'menu_id' => Menu::factory(),
            'parent_id' => null,
            'kind' => MenuItemKind::LINK,
            'category_public_id' => null,
            'label' => fake()->words(2, true),
            'url' => '/'.fake()->slug(),
            'icon' => null,
            'opens_in_new_tab' => false,
            'is_enabled' => true,
            'sort_order' => 0,
        ];
    }

    public function category(string $categoryPublicId, ?string $label = null): self
    {
        return $this->state(fn (array $attributes) => [
            'kind' => MenuItemKind::CATEGORY,
            'category_public_id' => $categoryPublicId,
            'url' => null,
            'label' => $label,
        ]);
    }

    public function categoryTree(?string $categoryPublicId = null, ?string $label = null): self
    {
        return $this->state(fn (array $attributes) => [
            'kind' => MenuItemKind::CATEGORY_TREE,
            'category_public_id' => $categoryPublicId,
            'url' => null,
            'label' => $label,
        ]);
    }

    public function childOf(MenuItem $parent): self
    {
        return $this->state(fn (array $attributes) => [
            'menu_id' => $parent->menu_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function forMenu(Menu $menu): self
    {
        return $this->state(fn (array $attributes) => [
            'menu_id' => $menu->id,
        ]);
    }
}
