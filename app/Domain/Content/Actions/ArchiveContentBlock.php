<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ArchiveContentBlock
{
    public function __invoke(int $id, ?User $causer = null): ContentBlock
    {
        return DB::transaction(function () use ($id, $causer) {
            $block = ContentBlock::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($block->status === PublishStatus::ARCHIVED) {
                return $block;
            }

            $oldStatus = $block->status;
            $block->status = PublishStatus::ARCHIVED;
            $block->updated_by = $causer?->id;
            $block->save();

            activity('content')
                ->performedOn($block)
                ->causedBy($causer)
                ->withProperties([
                    'old_status' => $oldStatus?->value,
                    'new_status' => PublishStatus::ARCHIVED->value,
                ])
                ->event('archived')
                ->log('archived');

            return $block;
        });
    }
}
