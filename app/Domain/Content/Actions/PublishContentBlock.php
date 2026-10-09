<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\UnservablePayloadException;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PublishContentBlock
{
    public function __construct(
        private readonly ResolveServablePayload $resolveServablePayload
    ) {}

    public function __invoke(int $id, ?User $causer = null): ContentBlock
    {
        return DB::transaction(function () use ($id, $causer) {
            $block = ContentBlock::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($block->status === PublishStatus::ARCHIVED) {
                throw new IllegalStateTransitionException('Cannot publish an archived block.');
            }

            if ($block->status === PublishStatus::PUBLISHED) {
                return $block;
            }

            $health = ($this->resolveServablePayload)($block);
            if (! $health->isServable()) {
                throw new UnservablePayloadException($health->outcome);
            }

            $oldStatus = $block->status;
            $block->status = PublishStatus::PUBLISHED;
            $block->updated_by = $causer?->id;
            $block->save();

            activity('content')
                ->performedOn($block)
                ->causedBy($causer)
                ->withProperties([
                    'old_status' => $oldStatus?->value,
                    'new_status' => PublishStatus::PUBLISHED->value,
                ])
                ->event('published')
                ->log('published');

            return $block;
        });
    }
}
