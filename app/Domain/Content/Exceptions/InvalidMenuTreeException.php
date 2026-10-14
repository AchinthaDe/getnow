<?php

declare(strict_types=1);

namespace App\Domain\Content\Exceptions;

final class InvalidMenuTreeException extends ContentException
{
    public static function titleTooLong(int $max): self
    {
        return new self("Menu title exceeds {$max} characters.");
    }

    public static function labelInvalid(string $msg): self
    {
        return new self("Invalid menu item label: {$msg}");
    }

    public static function urlInvalid(string $url): self
    {
        return new self("Invalid menu item URL [{$url}].");
    }

    public static function depthExceeded(): self
    {
        return new self('Menu structure exceeds maximum allowed depth of 2.');
    }

    public static function capsExceeded(string $msg): self
    {
        return new self("Menu item limit exceeded: {$msg}");
    }

    public static function iconNotSupported(): self
    {
        return new self('Icons are not supported in menu items.');
    }

    public static function unknownPublicId(string $publicId): self
    {
        return new self("Item [{$publicId}] does not belong to this menu.");
    }

    public static function duplicatePublicId(string $publicId): self
    {
        return new self("Duplicate item public ID [{$publicId}] submitted.");
    }
}
