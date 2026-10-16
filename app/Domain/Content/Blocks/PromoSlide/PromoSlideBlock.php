<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\PromoSlide;

use App\Domain\Content\Contracts\BlockDefinition;

final class PromoSlideBlock implements BlockDefinition
{
    public function typeKey(): string
    {
        return 'promo_slide';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function dataClass(): string
    {
        return PromoSlideData::class;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upgradePayload(int $fromVersion, array $payload): array
    {
        return $payload;
    }
}
