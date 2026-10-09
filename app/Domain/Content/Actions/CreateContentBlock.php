<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Data\CreateContentBlockData;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateContentBlock
{
    public function __construct(
        private readonly BlockRegistry $registry
    ) {}

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __invoke(CreateContentBlockData $data, array $rawPayload, ?User $causer = null): ContentBlock
    {
        $definition = $this->registry->get($data->type);

        if ($definition === null) {
            throw new UnknownBlockTypeException("Block type [{$data->type}] is not registered.");
        }

        if (! in_array($data->type, $data->placement->allowedBlockTypes(), true)) {
            throw ValidationException::withMessages([
                'type' => "Block type [{$data->type}] is not allowed in placement [{$data->placement->value}].",
            ]);
        }

        if ($data->starts_at !== null && $data->ends_at !== null && $data->ends_at->lte($data->starts_at)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The ends at date must be after the starts at date.',
            ]);
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

        $startsAt = $data->starts_at?->clone()->setTimezone('UTC');
        $endsAt = $data->ends_at?->clone()->setTimezone('UTC');

        return DB::transaction(function () use ($data, $payloadData, $definition, $causer, $startsAt, $endsAt) {
            $block = new ContentBlock;
            $block->fill([
                'placement' => $data->placement->value,
                'type' => $data->type,
                'schema_version' => $definition->schemaVersion(),
                'payload' => $payloadData->toArray(),
                'status' => PublishStatus::DRAFT->value,
                'is_enabled' => true,
                'sort_order' => 0,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => $causer?->id,
                'updated_by' => $causer?->id,
            ]);

            $block->save();

            activity('content')
                ->performedOn($block)
                ->causedBy($causer)
                ->withProperties([
                    'payload' => $payloadData->toArray(),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ])
                ->event('created')
                ->log('created');

            return $block;
        });
    }
}
