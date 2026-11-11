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

    public static function cycleDetected(string $publicId, string $parentPublicId): self
    {
        return new self("Cannot move category [{$publicId}] under itself or its descendant [{$parentPublicId}].");
    }

    public static function hasChildren(string $publicId): self
    {
        return new self("Cannot delete category [{$publicId}] because it has child categories.");
    }

    public static function inUse(string $publicId): self
    {
        return new self("Cannot delete category [{$publicId}] because it is in use. Deactivate it instead.");
    }

    public static function invalidSiblingOrder(?string $parentPublicId): self
    {
        return new self("The ordered list of category IDs does not match the current siblings of parent [{$parentPublicId}].");
    }

    public static function parentHasModels(string $parentPublicId): self
    {
        return new self("Cannot create child category under parent [{$parentPublicId}] because it has product models assigned.");
    }

    public static function newParentHasModels(string $parentPublicId): self
    {
        return new self("Cannot move category under parent [{$parentPublicId}] because it has product models assigned.");
    }
}
