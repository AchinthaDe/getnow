<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Data\BlockPayload;
use Illuminate\Contracts\Support\Arrayable;

final class TestFailedUpgradeBlock implements BlockDefinition
{
    public function typeKey(): string
    {
        return 'test_failed_upgrade_block';
    }

    public function schemaVersion(): int
    {
        return 2;
    }

    public function dataClass(): string
    {
        return get_class(new class extends BlockPayload
        {
            /**
             * @param  Arrayable<string, mixed>|array<string, mixed>  $payload
             */
            public static function validateAndCreate(Arrayable|array $payload): static
            {
                return new self;
            }
        });
    }

    public function upgradePayload(int $fromVersion, array $payload): array
    {
        if ($fromVersion === 1) {
            throw new \Exception('Simulated upgrade failure for testing.');
        }

        return $payload;
    }
}
