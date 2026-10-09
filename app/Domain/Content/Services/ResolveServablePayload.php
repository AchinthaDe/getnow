<?php

declare(strict_types=1);

namespace App\Domain\Content\Services;

use App\Domain\Content\Actions\GetUpgradedBlockPayload;
use App\Domain\Content\Data\ServingHealthResult;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Exceptions\InvalidPayloadException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Exceptions\UnsupportedSchemaVersionException;
use App\Domain\Content\Exceptions\UpgradeFailedException;
use App\Domain\Content\Models\ContentBlock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ResolveServablePayload
{
    public function __construct(
        private readonly GetUpgradedBlockPayload $getUpgradedBlockPayload
    ) {}

    public function __invoke(ContentBlock $block, bool $log = true): ServingHealthResult
    {
        try {
            $payload = ($this->getUpgradedBlockPayload)(
                $block->type,
                $block->schema_version,
                $block->payload
            );

            return new ServingHealthResult(
                outcome: ServingHealthOutcome::Servable,
                payload: $payload
            );
        } catch (UnknownBlockTypeException $e) {
            if ($log) {
                $this->logFailure($block, ServingHealthOutcome::UnknownType);
            }

            return new ServingHealthResult(ServingHealthOutcome::UnknownType);
        } catch (UnsupportedSchemaVersionException $e) {
            if ($log) {
                $this->logFailure($block, ServingHealthOutcome::UnsupportedVersion);
            }

            return new ServingHealthResult(ServingHealthOutcome::UnsupportedVersion);
        } catch (InvalidPayloadException $e) {
            if ($log) {
                $this->logFailure($block, ServingHealthOutcome::InvalidAfterUpgrade);
            }

            return new ServingHealthResult(ServingHealthOutcome::InvalidAfterUpgrade);
        } catch (UpgradeFailedException $e) {
            if ($log) {
                $this->logFailure($block, ServingHealthOutcome::UpgradeFailed);
            }

            return new ServingHealthResult(ServingHealthOutcome::UpgradeFailed);
        } catch (\Throwable $e) {
            // Fail closed for unexpected exceptions
            report($e);
            if ($log) {
                $this->logFailure($block, ServingHealthOutcome::UpgradeFailed);
            }

            return new ServingHealthResult(ServingHealthOutcome::UpgradeFailed);
        }
    }

    private function logFailure(ContentBlock $block, ServingHealthOutcome $outcome): void
    {
        $cacheKey = "serving_failure:{$block->public_id}:{$outcome->value}";
        $ttl = now()->addHour();

        try {
            $added = Cache::add($cacheKey, true, $ttl);
            if (! $added) {
                return; // Already logged recently
            }
        } catch (\Throwable $e) {
            // Cache failed; proceed to log unthrottled but do not throw
        }

        Log::warning('Content Block serving health failure', [
            'public_id' => $block->public_id,
            'type' => $block->type,
            'schema_version' => $block->schema_version,
            'reason' => $outcome->value,
        ]);
    }
}
