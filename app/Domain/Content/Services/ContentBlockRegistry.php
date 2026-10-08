<?php

declare(strict_types=1);

namespace App\Domain\Content\Services;

use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Contracts\BlockRegistry as BlockRegistryContract;
use App\Domain\Content\Exceptions\DuplicateBlockTypeException;

final class ContentBlockRegistry implements BlockRegistryContract
{
    /** @var array<string, BlockDefinition> */
    private array $blocks = [];

    public function register(BlockDefinition $definition): void
    {
        $key = $definition->typeKey();

        if (isset($this->blocks[$key])) {
            throw new DuplicateBlockTypeException("Block type [{$key}] is already registered.");
        }

        $this->blocks[$key] = $definition;
    }

    public function get(string $typeKey): ?BlockDefinition
    {
        return $this->blocks[$typeKey] ?? null;
    }

    public function all(): array
    {
        return $this->blocks;
    }
}
