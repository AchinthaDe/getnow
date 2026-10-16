<?php

declare(strict_types=1);

namespace App\Domain\Content\Blocks\SectionHeader;

use App\Domain\Content\Contracts\BlockDefinition;

final class SectionHeaderBlock implements BlockDefinition
{
    public function typeKey(): string
    {
        return 'section_header';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function dataClass(): string
    {
        return SectionHeaderData::class;
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
