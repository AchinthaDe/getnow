<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'public_id' => (string) Str::ulid(),
            'menu_id' => Menu::factory(),
            'parent_id' => null,
            'label' => fake()->words(2, true),
            'url' => '/'.fake()->slug(),
            'icon' => null,
            'opens_in_new_tab' => false,
            'is_enabled' => true,
            'sort_order' => 0,
        ];
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
