<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum MenuKey: string
{
    case MAIN_NAV = 'main_nav';
    case MOBILE_NAV = 'mobile_nav';
    case FOOTER_COLUMN_1 = 'footer_column_1';
    case FOOTER_COLUMN_2 = 'footer_column_2';
    case FOOTER_COLUMN_3 = 'footer_column_3';

    public function defaultLabel(): string
    {
        return match ($this) {
            self::MAIN_NAV => 'Main Navigation',
            self::MOBILE_NAV => 'Mobile Navigation',
            self::FOOTER_COLUMN_1 => 'Footer Column 1',
            self::FOOTER_COLUMN_2 => 'Footer Column 2',
            self::FOOTER_COLUMN_3 => 'Footer Column 3',
        };
    }

    /**
     * @return array<int, MenuItemKind>
     */
    public function allowedKinds(): array
    {
        return match ($this) {
            self::MAIN_NAV, self::MOBILE_NAV => [
                MenuItemKind::LINK,
                MenuItemKind::CATEGORY,
                MenuItemKind::CATEGORY_TREE,
            ],
            self::FOOTER_COLUMN_1, self::FOOTER_COLUMN_2, self::FOOTER_COLUMN_3 => [
                MenuItemKind::LINK,
                MenuItemKind::CATEGORY,
            ],
        };
    }
}
