<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\InvalidPayloadException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PublishContentBlock
{
    public function __construct(
        private readonly BlockRegistry $registry
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

            $definition = $this->registry->get($block->type);

            if ($definition === null) {
                throw new UnknownBlockTypeException("Block type [{$block->type}] is not registered.");
            }

            $schemaVersion = $block->schema_version;
            $currentVersion = $definition->schemaVersion();
            $payload = $block->payload;

            for ($v = $schemaVersion; $v < $currentVersion; $v++) {
                $payload = $definition->upgradePayload($v, $payload);
            }

            try {
                /** @var class-string<BlockPayload> $dataClass */
                $dataClass = $definition->dataClass();
                $dataClass::validateAndCreate($payload);
            } catch (ValidationException $e) {
                throw new InvalidPayloadException('Cannot publish because payload validation fails: '.$e->getMessage(), 0, $e);
            }

            $oldStatus = $block->status;
            $block->status = PublishStatus::PUBLISHED;
            $block->save();

            activity('content')
                ->performedOn($block)
                ->causedBy($causer)
                ->withProperties([
                    'old_status' => $oldStatus?->value,
                    'new_status' => PublishStatus::PUBLISHED->value,
                ])
                ->log('published');

            return $block;
        });
    }
}
