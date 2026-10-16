<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\AnnouncementBar;

use App\Domain\Content\Contracts\BlockPayloadUpgrader;

final class AnnouncementBarV1ToV2Upgrader implements BlockPayloadUpgrader
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function __invoke(array $payload): array
    {
        $payload['secondary_text'] = $payload['secondary_text'] ?? null;
        $payload['locale_text'] = $payload['locale_text'] ?? null;

        return $payload;
    }
}
