<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Exceptions;

use RuntimeException;

final class ProductModelRuleViolation extends RuntimeException
{
    public static function brandNotFound(string $publicId): self
    {
        return new self("Brand [{$publicId}] does not exist.");
    }

    public static function categoryNotFound(string $publicId): self
    {
        return new self("Category [{$publicId}] does not exist.");
    }

    public static function brandInactive(string $publicId): self
    {
        return new self("Brand [{$publicId}] is inactive.");
    }

    public static function categoryNotLeaf(string $publicId): self
    {
        return new self("Category [{$publicId}] has children and cannot receive product models.");
    }

    public static function categoryNotEffectivelyActive(string $publicId): self
    {
        return new self("Category [{$publicId}] is not effectively active.");
    }

    public static function duplicateName(string $name, string $brandPublicId): self
    {
        return new self("A product model named [{$name}] already exists for brand [{$brandPublicId}].");
    }

    public static function emptySlug(string $name): self
    {
        return new self("Product model name [{$name}] generates an empty slug.");
    }
}
