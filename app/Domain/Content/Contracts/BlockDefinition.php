<?php

declare(strict_types=1);

namespace App\Domain\Content\Contracts;

use App\Domain\Content\Data\BlockPayload;

interface BlockDefinition
{
    public function typeKey(): string;

    public function schemaVersion(): int;

    /**
     * @return class-string<BlockPayload>
     */
    public function dataClass(): string;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function upgradePayload(int $fromVersion, array $payload): array;
}
