<?php

declare(strict_types=1);

namespace App\Domain\Content\Contracts;

interface BlockPayloadUpgrader
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function __invoke(array $payload): array;
}
