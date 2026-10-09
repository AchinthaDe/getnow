<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Data\BlockServingStatusResult;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;

final class GetBlockServingStatus
{
    /**
     * @var array<string, BlockServingStatusResult>
     */
    private array $memo = [];

    public function __construct(
        private readonly ResolveServablePayload $resolveServablePayload
    ) {}

    public function __invoke(ContentBlock $block): BlockServingStatusResult
    {
        // Key based on the block's current state
        $updatedAt = $block->updated_at !== null ? $block->updated_at->timestamp : 0;
        $payloadString = json_encode($block->payload);
        $key = sprintf(
            '%d_%d_%s_%d_%d_%s_%d_%d',
            $block->id ?? 0,
            $updatedAt,
            $block->status ? $block->status->value : '',
            $block->is_enabled ? 1 : 0,
            $block->schema_version ?? 1,
            md5($payloadString ?: ''),
            $block->starts_at !== null ? $block->starts_at->timestamp : 0,
            $block->ends_at !== null ? $block->ends_at->timestamp : 0
        );

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        if (! $block->isLive()) {
            $result = new BlockServingStatusResult(ServingStatus::NotLive);
            $this->memo[$key] = $result;

            return $result;
        }

        $healthResult = ($this->resolveServablePayload)($block, false);

        if ($healthResult->outcome === ServingHealthOutcome::Servable) {
            $status = ServingStatus::Live;
        } else {
            $status = ServingStatus::PayloadError;
        }

        $result = new BlockServingStatusResult($status, $healthResult->outcome);
        $this->memo[$key] = $result;

        return $result;
    }
}
