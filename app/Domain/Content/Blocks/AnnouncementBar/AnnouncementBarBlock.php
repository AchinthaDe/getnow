<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\AnnouncementBar;

use App\Domain\Content\Contracts\BlockDefinition;

final class AnnouncementBarBlock implements BlockDefinition
{
    public function typeKey(): string
    {
        return 'announcement_bar';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function dataClass(): string
    {
        return AnnouncementBarData::class;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upgradePayload(int $fromVersion, array $payload): array
    {
        // No upgrades needed yet, version is 1.
        return $payload;
    }
}
