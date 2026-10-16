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
        return 2;
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
        if (isset($payload['trigger_upgrade_failure'])) {
            throw new \Exception('Triggered upgrade failure for testing.');
        }

        if ($fromVersion === 1) {
            return (new AnnouncementBarV1ToV2Upgrader)($payload);
        }

        return $payload;
    }
}
