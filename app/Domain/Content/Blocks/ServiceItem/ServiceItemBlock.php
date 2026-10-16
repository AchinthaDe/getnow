<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\ServiceItem;

use App\Domain\Content\Contracts\BlockDefinition;

final class ServiceItemBlock implements BlockDefinition
{
    public function typeKey(): string
    {
        return 'service_item';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function dataClass(): string
    {
        return ServiceItemData::class;
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
