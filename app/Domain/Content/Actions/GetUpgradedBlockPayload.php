<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Exceptions\InvalidPayloadException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Exceptions\UnsupportedSchemaVersionException;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Exceptions\CannotCreateData;

final class GetUpgradedBlockPayload
{
    public function __construct(
        private readonly BlockRegistry $registry
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(string $type, int $schemaVersion, array $payload): BlockPayload
    {
        $definition = $this->registry->get($type);

        if ($definition === null) {
            throw new UnknownBlockTypeException("Block type [{$type}] is not registered.");
        }

        $currentVersion = $definition->schemaVersion();

        if ($schemaVersion > $currentVersion) {
            throw new UnsupportedSchemaVersionException(
                "Stored schema version [{$schemaVersion}] is higher than current version [{$currentVersion}]."
            );
        }

        // Sequential upgrade
        for ($v = $schemaVersion; $v < $currentVersion; $v++) {
            try {
                $payload = $definition->upgradePayload($v, $payload);
            } catch (\Exception $e) {
                throw new InvalidPayloadException("Upgrade logic failed from v{$v} to v".($v + 1).': '.$e->getMessage(), 0, $e);
            }
        }

        try {
            /** @var class-string<BlockPayload> $dataClass */
            $dataClass = $definition->dataClass();

            return $dataClass::validateAndCreate($payload);
        } catch (ValidationException $e) {
            throw new InvalidPayloadException('Payload validation failed after upgrade: '.$e->getMessage(), 0, $e);
        } catch (CannotCreateData $e) {
            throw new InvalidPayloadException('Cannot create payload data after upgrade: '.$e->getMessage(), 0, $e);
        }
    }
}
