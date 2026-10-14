<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<Menu>
 */
final class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'key' => MenuKey::MAIN_NAV,
            'label' => 'Main Navigation',
            'title' => null,
        ];
    }

    public function forKey(MenuKey $key): self
    {
        return $this->state(fn (array $attributes) => [
            'key' => $key,
            'label' => $key->defaultLabel(),
        ]);
    }

    public function create($attributes = [], ?Model $parent = null)
    {
        $rawAttributes = $this->raw($attributes, $parent);
        $key = $rawAttributes['key'] ?? MenuKey::MAIN_NAV;
        $keyVal = $key instanceof MenuKey ? $key->value : $key;

        $existing = Menu::where('key', $keyVal)->first();
        if ($existing !== null) {
            if (is_array($attributes) && array_key_exists('title', $attributes)) {
                $existing->update(['title' => $attributes['title']]);
            }

            return $existing;
        }

        return parent::create($attributes, $parent);
    }
}
