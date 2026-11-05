<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

enum CategoryTreeAudience: string
{
    case Admin = 'admin';
    case Storefront = 'storefront';
}
