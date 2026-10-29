<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\ContentBlockPreviewData;
use App\Domain\Content\Data\LayoutBlockData;
use App\Domain\Content\Data\LayoutData;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Exceptions\ContentException;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

final class ResolveContentBlockPreview
{
    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly GetUpgradedBlockPayload $getUpgradedBlockPayload,
        private readonly ResolveServablePayload $resolveServablePayload,
        private readonly GetLayoutContent $getLayoutContent,
    ) {}

    public function __invoke(
        ContentBlock $contentBlock,
        int $userId,
        bool $includeFullLayout = false,
        bool $useEphemeralCache = false,
    ): ContentBlockPreviewData {
        $placement = $contentBlock->placement;
        $definition = $this->registry->get($contentBlock->type);

        if (! $placement instanceof Placement || $definition === null) {
            return new ContentBlockPreviewData(
                block: null,
                placement: $placement instanceof Placement ? $placement : null,
                layout: $includeFullLayout ? ($this->getLayoutContent)()->data : null,
                usingFallbackPayload: false,
                isUnservable: true,
            );
        }

        $block = null;
        $usingFallbackPayload = false;
        $isUnservable = false;
        $cachedData = null;

        if ($useEphemeralCache && Cache::has(SaveContentBlockPreviewState::cacheKey($userId, $contentBlock->public_id))) {
            /** @var array{payload: array<string, mixed>, sort_order: int, token: string} $cachedData */
            $cachedData = Cache::get(SaveContentBlockPreviewState::cacheKey($userId, $contentBlock->public_id));
            try {
                $upgradedDraft = ($this->getUpgradedBlockPayload)(
                    $contentBlock->type,
                    $definition->schemaVersion(),
                    $cachedData['payload'],
                );
                $block = new LayoutBlockData(
                    id: $contentBlock->public_id,
                    type: $contentBlock->type,
                    version: $definition->schemaVersion(),
                    data: $upgradedDraft->toArray(),
                );
                $usingFallbackPayload = false;
                $isUnservable = false;
            } catch (ContentException|ValidationException) {
                $usingFallbackPayload = true;
            }
        }

        if ($block === null) {
            $health = ($this->resolveServablePayload)($contentBlock, log: false);
            if ($health->isServable() && $health->payload !== null) {
                $block = new LayoutBlockData(
                    id: $contentBlock->public_id,
                    type: $contentBlock->type,
                    version: $definition->schemaVersion(),
                    data: $health->payload->toArray(),
                );
                $isUnservable = false;
            } else {
                $isUnservable = true;
                $block = null;
                $usingFallbackPayload = false;
            }
        }

        $layout = null;
        if ($includeFullLayout) {
            $baseLayout = ($this->getLayoutContent)()->data;
            $placements = $baseLayout->placements;

            if ($block !== null) {
                if ($placement->maxCount() === 1) {
                    $placements[$placement->value] = [$block];
                } elseif ($placement->maxCount() > 1) {
                    /** @var Collection<int, ContentBlock> $candidates */
                    $candidates = ContentBlock::visible()
                        ->where('placement', $placement->value)
                        ->where('id', '!=', $contentBlock->id)
                        ->servingOrder()
                        ->get();

                    $allowedTypes = $placement->allowedBlockTypes();
                    $candidateEntries = [];

                    foreach ($candidates as $candidate) {
                        if (! in_array($candidate->type, $allowedTypes, true)) {
                            continue;
                        }

                        $candHealth = ($this->resolveServablePayload)($candidate, log: false);
                        if (! $candHealth->isServable() || $candHealth->payload === null) {
                            continue;
                        }

                        $candDef = $this->registry->get($candidate->type);
                        if ($candDef === null) {
                            continue;
                        }

                        $candidateEntries[] = [
                            'block' => new LayoutBlockData(
                                id: $candidate->public_id,
                                type: $candidate->type,
                                version: $candDef->schemaVersion(),
                                data: $candHealth->payload->toArray(),
                            ),
                            'sort_order' => $candidate->sort_order,
                            'starts_at' => $candidate->starts_at?->timestamp,
                            'id' => $candidate->id,
                            'is_target' => false,
                        ];
                    }

                    $targetSortOrder = ($useEphemeralCache && isset($cachedData['sort_order']))
                        ? (int) $cachedData['sort_order']
                        : $contentBlock->sort_order;

                    $targetEntry = [
                        'block' => $block,
                        'sort_order' => $targetSortOrder,
                        'starts_at' => $contentBlock->starts_at?->timestamp,
                        'id' => $contentBlock->id,
                        'is_target' => true,
                    ];

                    $allEntries = array_merge($candidateEntries, [$targetEntry]);

                    usort($allEntries, function (array $a, array $b): int {
                        // 1. sort_order ASC
                        if ($a['sort_order'] !== $b['sort_order']) {
                            return $a['sort_order'] <=> $b['sort_order'];
                        }

                        // 2. starts_at DESC NULLS LAST
                        $aStarts = $a['starts_at'];
                        $bStarts = $b['starts_at'];
                        if ($aStarts !== null && $bStarts === null) {
                            return -1;
                        }
                        if ($aStarts === null && $bStarts !== null) {
                            return 1;
                        }
                        if ($aStarts !== null && $aStarts !== $bStarts) {
                            return $bStarts <=> $aStarts;
                        }

                        // 3. id DESC
                        return $b['id'] <=> $a['id'];
                    });

                    $maxCount = $placement->maxCount();
                    if (count($allEntries) > $maxCount) {
                        $topEntries = array_slice($allEntries, 0, $maxCount);
                        $hasTarget = false;
                        foreach ($topEntries as $entry) {
                            if ($entry['is_target']) {
                                $hasTarget = true;
                                break;
                            }
                        }
                        if (! $hasTarget) {
                            $topEntries = array_slice($allEntries, 0, $maxCount - 1);
                            $topEntries[] = $targetEntry;
                        }
                        $allEntries = $topEntries;
                    }

                    $placements[$placement->value] = array_map(
                        fn (array $e): LayoutBlockData => $e['block'],
                        $allEntries,
                    );
                }
            } else {
                if ($placement->maxCount() === 1) {
                    $placements[$placement->value] = [];
                }
            }

            $layout = new LayoutData(
                settings: $baseLayout->settings,
                placements: $placements,
                menus: $baseLayout->menus,
            );
        }

        return new ContentBlockPreviewData(
            block: $block,
            placement: $placement,
            layout: $layout,
            usingFallbackPayload: $usingFallbackPayload,
            isUnservable: $isUnservable,
        );
    }
}
