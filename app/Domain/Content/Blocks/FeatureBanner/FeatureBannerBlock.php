<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\FeatureBanner;

use App\Domain\Content\Contracts\BlockDefinition;

final class FeatureBannerBlock implements BlockDefinition
{
    public function typeKey(): string
    {
        return 'feature_banner';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function dataClass(): string
    {
        return FeatureBannerData::class;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upgradePayload(int $fromVersion, array $payload): array
    {
        return $payload;
    }

    public function defaultMaxLivePerPlacement(): int
    {
        return 1;
    }
}
