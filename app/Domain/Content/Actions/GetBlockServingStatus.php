<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Data\BlockServingStatusResult;
use App\Domain\Content\Data\ServingHealthResult;
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
        if (! $block->isLive()) {
            return new BlockServingStatusResult(ServingStatus::NotLive);
        }

        $updatedAt = $block->updated_at !== null ? $block->updated_at->timestamp : 0;
        $payloadString = json_encode($block->payload);
        $payloadHash = md5($payloadString ?: '');
        $key = sprintf(
            'serving_status_%d_%s_%d_%d_%s',
            $block->id ?? 0,
            $block->type,
            $updatedAt,
            $block->schema_version ?? 1,
            $payloadHash
        );

        return Cache::store('array')->rememberForever($key, function () use ($block, $updatedAt, $payloadHash): BlockServingStatusResult {
            $healthKey = sprintf(
                'candidate_servable_health_%d_%s_%d_%d_%s',
                $block->id ?? 0,
                $block->type,
                $updatedAt,
                $block->schema_version ?? 1,
                $payloadHash
            );
            $healthResult = Cache::store('array')->rememberForever(
                $healthKey,
                fn (): ServingHealthResult => ($this->resolveServablePayload)($block, false)
            );

            if (! $healthResult->isServable()) {
                return new BlockServingStatusResult(ServingStatus::PayloadError, $healthResult->outcome);
            }

            if ($block->placement === null) {
                return new BlockServingStatusResult(ServingStatus::NotLive);
            }

            $candidates = ContentBlock::visible()
                ->where('placement', $block->placement->value)
                ->servingOrder()
                ->get();

            $servableCount = 0;
            $isServed = false;
            $maxCount = $block->placement->maxCount();

            foreach ($candidates as $candidate) {
                if ($candidate->id === $block->id) {
                    $candHealth = $healthResult;
                } else {
                    $candUpdatedAt = $candidate->updated_at !== null ? $candidate->updated_at->timestamp : 0;
                    $candPayloadString = json_encode($candidate->payload);
                    $candKey = sprintf(
                        'candidate_servable_health_%d_%s_%d_%d_%s',
                        $candidate->id ?? 0,
                        $candidate->type,
                        $candUpdatedAt,
                        $candidate->schema_version ?? 1,
                        md5($candPayloadString ?: '')
                    );
                    $candHealth = Cache::store('array')->rememberForever(
                        $candKey,
                        fn (): ServingHealthResult => ($this->resolveServablePayload)($candidate, false)
                    );
                }

                if (! $candHealth->isServable()) {
                    continue;
                }

                $servableCount++;
                if ($candidate->id === $block->id) {
                    $isServed = true;
                    break;
                }

                if ($servableCount >= $maxCount) {
                    break;
                }
            }

            if ($isServed) {
                return new BlockServingStatusResult(ServingStatus::Live, ServingHealthOutcome::Servable);
            }

            return new BlockServingStatusResult(ServingStatus::NotLive);
        });
    }
}
