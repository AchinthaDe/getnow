<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateContentBlock
{
    public function __construct(
        private readonly BlockRegistry $registry
    ) {}

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __invoke(int $id, UpdateContentBlockData $data, array $rawPayload, ?User $causer = null): ContentBlock
    {
        if ($data->starts_at !== null && $data->ends_at !== null && $data->ends_at->lte($data->starts_at)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The ends at date must be after the starts at date.',
            ]);
        }

        return DB::transaction(function () use ($id, $data, $rawPayload, $causer) {
            $block = ContentBlock::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($block->status === PublishStatus::ARCHIVED) {
                throw new IllegalStateTransitionException('Cannot update an archived block.');
            }

            $definition = $this->registry->get($block->type);

            if ($definition === null) {
                throw new UnknownBlockTypeException("Block type [{$block->type}] is not registered.");
            }

            /** @var class-string<BlockPayload> $dataClass */
            $dataClass = $definition->dataClass();
            $payloadData = $dataClass::validateAndCreate($rawPayload);

            $isDirty = $block->payload != $payloadData->toArray() ||
                $block->starts_at?->toDateTimeString() !== $data->starts_at?->toDateTimeString() ||
                $block->ends_at?->toDateTimeString() !== $data->ends_at?->toDateTimeString();

            $block->fill([
                'payload' => $payloadData->toArray(),
                'starts_at' => $data->starts_at,
                'ends_at' => $data->ends_at,
            ]);

            if ($isDirty) {
                $block->schema_version = $definition->schemaVersion();
                $block->updated_by = $causer?->id;
                $block->save();

                activity('content')
                    ->performedOn($block)
                    ->causedBy($causer)
                    ->log('updated');
            }

            return $block;
        });
    }
}
