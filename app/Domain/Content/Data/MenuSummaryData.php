<?php

declare(strict_types=1);

namespace App\Domain\Content\Data;

use App\Domain\Content\Enums\MenuKey;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

final class MenuSummaryData extends Data
{
    public function __construct(
        public string $public_id,
        public MenuKey $key,
        public string $label,
        public ?string $title,
        public int $item_count,
        public ?CarbonInterface $updated_at,
    ) {}
}
