<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Exceptions;

use RuntimeException;

final class BrandRuleViolation extends RuntimeException
{
    public static function emptySlug(string $name): self
    {
        return new self("Brand name [{$name}] generates an empty slug.");
    }

    public static function duplicateName(string $name): self
    {
        return new self("A brand named [{$name}] already exists.");
    }
}
