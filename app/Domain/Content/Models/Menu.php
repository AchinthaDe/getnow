<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\MenuKey;
use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property MenuKey $key
 * @property string $label
 * @property string|null $title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'key' => MenuKey::class,
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function newFactory(): MenuFactory
    {
        return MenuFactory::new();
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function rootItems(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'menu_id')
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }
}
