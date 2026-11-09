<?php

declare(strict_types=1);

namespace App\Domain\Content\Exceptions;

use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Enums\MenuKey;

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

    public static function disallowedKind(MenuItemKind $kind, MenuKey $menuKey): self
    {
        return new self("Menu [{$menuKey->value}] does not allow items of kind [{$kind->value}].");
    }

    public static function urlRequiredForLink(): self
    {
        return new self('URL is required for link menu items.');
    }

    public static function urlNotAllowedForKind(MenuItemKind $kind): self
    {
        return new self("URL is not allowed for menu items of kind [{$kind->value}].");
    }

    public static function categoryReferenceRequired(): self
    {
        return new self('Category reference is required for category menu items.');
    }

    public static function categoryReferenceNotAllowedForKind(MenuItemKind $kind): self
    {
        return new self("Category reference is not allowed for menu items of kind [{$kind->value}].");
    }

    public static function categoryNotFound(string $publicId): self
    {
        return new self("Referenced category [{$publicId}] does not exist.");
    }

    public static function categoryDeletedDuringSave(): self
    {
        return new self('A referenced category was deleted during save.');
    }

    public static function categoryTreeAtLevelOneOnly(): self
    {
        return new self('Category tree items are allowed at level 1 only.');
    }

    public static function categoryTreeCannotHaveChildren(): self
    {
        return new self('Category tree items cannot define child items.');
    }
}
