<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property string $label
 * @property string $url
 * @property string|null $icon
 * @property bool $opens_in_new_tab
 * @property bool $is_enabled
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'opens_in_new_tab' => 'boolean',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function newFactory(): MenuItemFactory
    {
        return MenuItemFactory::new();
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    /**
     * @return BelongsTo<MenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')
            ->orderBy('sort_order');
    }
}
