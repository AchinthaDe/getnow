<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class EnableContentBlock
{
    public function __invoke(int $id, ?User $causer = null): ContentBlock
    {
        return DB::transaction(function () use ($id, $causer) {
            $block = ContentBlock::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($block->status === PublishStatus::ARCHIVED) {
                throw new IllegalStateTransitionException('Cannot enable an archived block.');
            }

            if ($block->is_enabled === true) {
                return $block;
            }

            $oldEnabled = $block->is_enabled;
            $block->is_enabled = true;
            $block->updated_by = $causer?->id;
            $block->save();

            activity('content')
                ->performedOn($block)
                ->causedBy($causer)
                ->withProperties([
                    'old_is_enabled' => $oldEnabled,
                    'new_is_enabled' => true,
                ])
                ->event('enabled')
                ->log('enabled');

            return $block;
        });
    }
}
