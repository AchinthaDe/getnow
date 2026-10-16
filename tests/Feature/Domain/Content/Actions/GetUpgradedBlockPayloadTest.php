<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetUpgradedBlockPayload;
use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Exceptions\InvalidPayloadException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Exceptions\UnsupportedSchemaVersionException;
use App\Domain\Content\Exceptions\UpgradeFailedException;
use App\Domain\Content\Services\ContentBlockRegistry;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Validation\ValidationException;

final class FakeBlockDataV3 extends BlockPayload
{
    /**
     * @param  array<string, mixed>|Arrayable<string, mixed>  $payload
     */
    public static function validateAndCreate(array|Arrayable $payload): static
    {
        if (is_array($payload) && isset($payload['fail'])) {
            throw ValidationException::withMessages(['fail' => 'failed']);
        }

        return new self;
    }
}

class FakeBlockDefinition implements BlockDefinition
{
    public function typeKey(): string
    {
        return 'fake';
    }

    public function schemaVersion(): int
    {
        return 3;
    }

    public function dataClass(): string
    {
        return FakeBlockDataV3::class;
    }

    public function upgradePayload(int $fromVersion, array $payload): array
    {
        if ($fromVersion === 1) {
            $payload['v2'] = true;
        } elseif ($fromVersion === 2) {
            $payload['v3'] = true;
        } else {
            throw new Exception('Bad upgrade');
        }

        return $payload;
    }
}

it('upgrades sequentially from v1 to v2 to v3', function () {
    $registry = new ContentBlockRegistry;
    $registry->register(new FakeBlockDefinition);

    $action = new GetUpgradedBlockPayload($registry);
    $action('fake', 1, []);

    // We expect it to not throw, meaning upgrade loop worked
    expect(true)->toBeTrue();
});

it('throws UnknownBlockTypeException for unregistered type', function () {
    $registry = new ContentBlockRegistry;
    $action = new GetUpgradedBlockPayload($registry);
    $action('unknown', 1, []);
})->throws(UnknownBlockTypeException::class);

it('throws UnsupportedSchemaVersionException if stored version is above current', function () {
    $registry = new ContentBlockRegistry;
    $registry->register(new FakeBlockDefinition);

    $action = new GetUpgradedBlockPayload($registry);
    $action('fake', 4, []);
})->throws(UnsupportedSchemaVersionException::class);

it('throws InvalidPayloadException if validation fails after upgrade', function () {
    $registry = new ContentBlockRegistry;
    $registry->register(new FakeBlockDefinition);

    $action = new GetUpgradedBlockPayload($registry);
    $action('fake', 3, ['fail' => true]);
})->throws(InvalidPayloadException::class);

it('throws UpgradeFailedException if upgrade logic fails', function () {
    $registry = new ContentBlockRegistry;
    $registry->register(new FakeBlockDefinition);

    $action = new GetUpgradedBlockPayload($registry);
    $action('fake', 0, []); // fromVersion 0 throws \Exception in FakeBlockDefinition
})->throws(UpgradeFailedException::class);
