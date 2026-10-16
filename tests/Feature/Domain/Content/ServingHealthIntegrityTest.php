<?php

declare(strict_types=1);
use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarData;
use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

it('updates schema_version properly during UpdateContentBlock', function () {
    // 1. Create a block at an old schema_version
    // We will use announcement_bar since its current schema_version is 1. We'll fake it in DB as 0 or we'll register a fake definition.
    // Actually, announcement_bar current schema is 1. Let's register a fake definition with version 2 for the test, or just use announcement_bar and set its DB version to 0.

    // Wait, announcement_bar has no upgrader from 0 to 1 since 1 is the base.
    // Let's create a custom BlockDefinition and register it for the test.

    $def = new class implements BlockDefinition
    {
        public function typeKey(): string
        {
            return 'test_upgrade_block';
        }

        public function schemaVersion(): int
        {
            return 2;
        }

        public function dataClass(): string
        {
            // We use an anonymous class extending BlockPayload for testing
            return get_class(new class extends BlockPayload
            {
                public bool $upgraded = false;

                /**
                 * @param  Arrayable<string, mixed>|array<string, mixed>  $payload
                 */
                public static function validateAndCreate(Arrayable|array $payload): static
                {
                    $instance = new self;
                    if (isset($payload['upgraded'])) {
                        $instance->upgraded = (bool) $payload['upgraded'];
                    }

                    return $instance;
                }
            });
        }

        public int $upgradeCalls = 0;

        public function upgradePayload(int $fromVersion, array $payload): array
        {
            $this->upgradeCalls++;
            if ($fromVersion === 1) {
                $payload['upgraded'] = true;
            }

            return $payload;
        }
    };

    app(BlockRegistry::class)->register($def);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'test_upgrade_block',
        'schema_version' => 1,
        'payload' => ['old_data' => true],
    ]);

    $user = User::factory()->create();

    // 2. Resolve to get upgraded payload
    $result = app(ResolveServablePayload::class)($block);
    expect($result->isServable())->toBeTrue();
    // Upgraded payload array:
    $upgradedArray = $result->payload->toArray();
    expect($upgradedArray)->toHaveKey('upgraded', true);

    // 3. Build UpdateContentBlockData from the UPGRADED payload
    $dto = new UpdateContentBlockData(
        starts_at: $block->starts_at,
        ends_at: $block->ends_at
    );

    // 4. Run UpdateContentBlock
    $action = app(UpdateContentBlock::class);
    $action($block->id, $dto, $upgradedArray, $user);

    // 5. Assert stored schema_version equals the definition's current version
    $block->refresh();
    expect($block->schema_version)->toBe(2)
        ->and($block->payload)->toEqual($upgradedArray);

    // 6. Assert resolving again does not upgrade twice
    // We can spy on the upgrader or just ensure it resolves fine
    $result2 = app(ResolveServablePayload::class)($block);
    expect($result2->isServable())->toBeTrue()
        ->and($def->upgradeCalls)->toBe(1); // One upgrade during the first resolve, ZERO on the second
});

it('updates schema_version properly using real validation during UpdateContentBlock', function () {
    $def = new class implements BlockDefinition
    {
        public function typeKey(): string
        {
            return 'test_validating_upgrade_block';
        }

        public function schemaVersion(): int
        {
            return 2;
        }

        public function dataClass(): string
        {
            return AnnouncementBarData::class;
        }

        public int $upgradeCalls = 0;

        public function upgradePayload(int $fromVersion, array $payload): array
        {
            $this->upgradeCalls++;
            if ($fromVersion === 1) {
                // Must return a shape valid for AnnouncementBarData
                $payload['text'] = 'Valid Upgraded Text';
                $payload['tone'] = 'info';
                $payload['link_url'] = null;
            }

            return $payload;
        }
    };

    app(BlockRegistry::class)->register($def);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'test_validating_upgrade_block',
        'schema_version' => 1,
        'payload' => ['old_garbage' => true],
    ]);

    $user = User::factory()->create();

    $result = app(ResolveServablePayload::class)($block);
    expect($result->isServable())->toBeTrue();
    $upgradedArray = $result->payload->toArray();
    expect($upgradedArray)->toHaveKey('text', 'Valid Upgraded Text');

    $dto = new UpdateContentBlockData(
        starts_at: $block->starts_at,
        ends_at: $block->ends_at
    );

    $action = app(UpdateContentBlock::class);
    $action($block->id, $dto, $upgradedArray, $user);

    $block->refresh();
    expect($block->schema_version)->toBe(2)
        ->and($block->payload)->toEqual($upgradedArray);
});
