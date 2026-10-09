<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Data\BlockServingStatusResult;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use Illuminate\Support\Facades\Cache;

final class GetBlockServingStatus
{
    public function __construct(
        private readonly ResolveServablePayload $resolveServablePayload
    ) {}

    public function __invoke(ContentBlock $block): BlockServingStatusResult
    {
        // Key based on the block's current state
        $updatedAt = $block->updated_at !== null ? $block->updated_at->timestamp : 0;
        $payloadString = json_encode($block->payload);
        $key = sprintf(
            'serving_status_%d_%s_%d_%s_%d_%d_%s_%d_%d',
            $block->id ?? 0,
            $block->type,
            $updatedAt,
            $block->status ? $block->status->value : '',
            $block->is_enabled ? 1 : 0,
            $block->schema_version ?? 1,
            md5($payloadString ?: ''),
            $block->starts_at !== null ? $block->starts_at->timestamp : 0,
            $block->ends_at !== null ? $block->ends_at->timestamp : 0
        );

        return Cache::store('array')->rememberForever($key, function () use ($block) {
            if (! $block->isLive()) {
                return new BlockServingStatusResult(ServingStatus::NotLive);
            }

            $healthResult = ($this->resolveServablePayload)($block, false);

            if ($healthResult->outcome === ServingHealthOutcome::Servable) {
                $status = ServingStatus::Live;
            } else {
                $status = ServingStatus::PayloadError;
            }

            return new BlockServingStatusResult($status, $healthResult->outcome);
        });
    }
}
