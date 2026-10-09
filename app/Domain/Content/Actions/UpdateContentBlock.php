<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Exceptions\UnsupportedSchemaVersionException;
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

            if ($block->schema_version > $definition->schemaVersion()) {
                throw new UnsupportedSchemaVersionException(
                    "Stored schema version [{$block->schema_version}] is higher than current version [{$definition->schemaVersion()}]."
                );
            }

            /** @var class-string<BlockPayload> $dataClass */
            $dataClass = $definition->dataClass();
            try {
                $payloadData = $dataClass::validateAndCreate($rawPayload);
            } catch (ValidationException $e) {
                $errors = [];
                foreach ($e->errors() as $key => $messages) {
                    $errors["payload.{$key}"] = $messages;
                }
                throw ValidationException::withMessages($errors);
            }

            $oldPayload = $block->payload;
            $newPayload = $payloadData->toArray();

            $sort = function (array &$array) use (&$sort) {
                ksort($array);
                foreach ($array as &$value) {
                    if (is_array($value)) {
                        $sort($value);
                    }
                }
            };

            $sort($oldPayload);
            $sort($newPayload);

            $startsAt = $data->starts_at?->clone()->setTimezone('UTC');
            $endsAt = $data->ends_at?->clone()->setTimezone('UTC');

            $changed = [];
            if (json_encode($oldPayload, JSON_THROW_ON_ERROR) !== json_encode($newPayload, JSON_THROW_ON_ERROR)) {
                $changed[] = 'payload';
            }
            if ($block->starts_at?->clone()->setTimezone('UTC')->format('U.u') !== $startsAt?->format('U.u')) {
                $changed[] = 'starts_at';
            }
            if ($block->ends_at?->clone()->setTimezone('UTC')->format('U.u') !== $endsAt?->format('U.u')) {
                $changed[] = 'ends_at';
            }
            if ($block->schema_version !== $definition->schemaVersion()) {
                $changed[] = 'schema_version';
            }

            $isDirty = $changed !== [];

            if ($isDirty) {
                $oldSchemaVersion = $block->schema_version;
                $oldStartsAt = $block->starts_at;
                $oldEndsAt = $block->ends_at;

                $block->fill([
                    'payload' => $payloadData->toArray(),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ]);

                $block->schema_version = $definition->schemaVersion();
                $block->updated_by = $causer?->id;
                $block->save();

                activity('content')
                    ->performedOn($block)
                    ->causedBy($causer)
                    ->withProperties([
                        'old_payload' => $oldPayload,
                        'new_payload' => $newPayload,
                        'old_starts_at' => $oldStartsAt,
                        'new_starts_at' => $startsAt,
                        'old_ends_at' => $oldEndsAt,
                        'new_ends_at' => $endsAt,
                        'old_schema_version' => $oldSchemaVersion,
                        'new_schema_version' => $block->schema_version,
                        'changed' => $changed,
                    ])
                    ->event('updated')
                    ->log('updated');
            }

            return $block;
        });
    }
}
