<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Exceptions;

use RuntimeException;

final class CategoryRuleViolation extends RuntimeException
{
    public static function maxDepthExceeded(int $maxDepth): self
    {
        return new self("Category nesting exceeds maximum depth of {$maxDepth}.");
    }

    public static function emptySlug(string $name): self
    {
        return new self("Category name [{$name}] generates an empty slug.");
    }

    public static function siblingNameClash(string $name, ?string $parentPublicId): self
    {
        return new self("A sibling category named [{$name}] already exists under parent [{$parentPublicId}].");
    }

    public static function parentNotFound(string $parentPublicId): self
    {
        return new self("Parent category [{$parentPublicId}] does not exist.");
    }
}
