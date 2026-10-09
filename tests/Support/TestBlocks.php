<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Content\Contracts\BlockRegistry;

final class TestBlocks
{
    public static function register(): void
    {
        /** @var BlockRegistry $registry */
        $registry = app(BlockRegistry::class);

        $registry->register(new TestFailedUpgradeBlock);
    }
}
