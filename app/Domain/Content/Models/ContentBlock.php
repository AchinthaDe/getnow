<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Content\Casts\TolerantEnum;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use Carbon\CarbonInterface;
use Database\Factories\ContentBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property Placement|null $placement
 * @property string $type
 * @property int $schema_version
 * @property array<string, mixed> $payload
 * @property PublishStatus|null $status
 * @property bool $is_enabled
 * @property int $sort_order
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ContentBlock extends Model
{
    /** @use HasFactory<ContentBlockFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'placement' => TolerantEnum::class.':'.Placement::class,
        'status' => PublishStatus::class,
        'payload' => 'array',
        'is_enabled' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function newFactory(): ContentBlockFactory
    {
        return ContentBlockFactory::new();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', PublishStatus::PUBLISHED->value)
            ->where('is_enabled', true)
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeServingOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')
            ->orderByRaw('starts_at DESC NULLS LAST')
            ->orderBy('id', 'desc');
    }

    public function isLiveAt(CarbonInterface $time): bool
    {
        if ($this->status !== PublishStatus::PUBLISHED) {
            return false;
        }

        if (! $this->is_enabled) {
            return false;
        }

        if ($this->starts_at !== null && $time->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $time->gte($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function isLive(): bool
    {
        return $this->isLiveAt(now());
    }
}
