<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Models\User;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property int $sort_order
 * @property bool $show_in_nav
 * @property bool $is_active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool|int|null $is_effectively_active
 * @property-read Category|null $parent
 * @property-read Collection<int, Category> $children
 * @property-read User|null $creator
 * @property-read User|null $updater
 */
final class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use HasUlids;

    public const int MAX_DEPTH = 2;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'show_in_nav' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }

    /**
     * PostgreSQL expression returning true if this category and every ancestor has is_active = true.
     * Uses UNION to terminate on cycles without recursion loops.
     */
    public static function effectiveActiveRawSql(): string
    {
        return 'NOT EXISTS (
            WITH RECURSIVE ancestors AS (
                SELECT c_anc.id, c_anc.parent_id, c_anc.is_active
                FROM categories c_anc
                WHERE c_anc.id = categories.id
                UNION
                SELECT parent.id, parent.parent_id, parent.is_active
                FROM categories parent
                JOIN ancestors ON parent.id = ancestors.parent_id
            )
            SELECT 1 FROM ancestors WHERE ancestors.is_active = false
        )';
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeEffectivelyActive(Builder $query): Builder
    {
        return $query->whereRaw(self::effectiveActiveRawSql());
    }

    /**
     * @param  Builder<Category>  $query
     * @return Builder<Category>
     */
    public function scopeWithEffectiveActive(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('categories.*');
        }

        return $query->selectRaw('('.self::effectiveActiveRawSql().') AS is_effectively_active');
    }
}
