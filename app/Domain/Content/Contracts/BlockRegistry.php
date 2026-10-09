<?php

declare(strict_types=1);

namespace App\Domain\Content\Contracts;

interface BlockRegistry
{
    public function register(BlockDefinition $definition): void;

    public function get(string $typeKey): ?BlockDefinition;

    /**
     * @return array<string, BlockDefinition>
     */
    public function all(): array;
}
