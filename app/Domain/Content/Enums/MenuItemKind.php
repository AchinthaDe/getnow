<?php

declare(strict_types=1);

namespace App\Domain\Content\Enums;

enum MenuItemKind: string
{
    case LINK = 'link';
    case CATEGORY = 'category';
    case CATEGORY_TREE = 'category_tree';
}
