<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Support;

use Illuminate\Support\Facades\DB;

final class CategorySiblingLock
{
    public static function acquire(?int $parentId): void
    {
        $idKey = $parentId ?? 0;
        $lockKey = (int) (crc32("category_parent:{$idKey}") & 0x7FFFFFFF);
        DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
    }
}
