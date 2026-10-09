<?php

declare(strict_types=1);

use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarData;
use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ContentBlockRegistry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

it('updates block and logs activity', function () {
    $registry = app(BlockRegistry::class);
    $action = new UpdateContentBlock($registry);

    $user = User::factory()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'schema_version' => 1,
        'payload' => ['text' => 'old', 'tone' => 'info'],
    ]);

    $data = new UpdateContentBlockData(
        starts_at: null,
        ends_at: null,
    );

    $updated = $action(
        id: $block->id,
        data: $data,
        rawPayload: ['text' => 'new text', 'tone' => 'success'],
        causer: $user
    );

    expect($updated->payload)->toBe(['text' => 'new text', 'link_url' => null, 'tone' => 'success'])
        ->and($updated->updated_by)->toBe($user->id);

    \Pest\Laravel\assertDatabaseHas('activity_log', [
        'subject_type' => ContentBlock::class,
        'subject_id' => $block->id,
        'causer_id' => $user->id,
        'description' => 'updated',
    ]);
});

it('rejects archived blocks', function () {
    $registry = app(BlockRegistry::class);
    $action = new UpdateContentBlock($registry);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->archived()->create();
    $data = new UpdateContentBlockData(null, null);

    expect(fn () => $action($block->id, $data, ['text' => 'x', 'tone' => 'info']))
        ->toThrow(IllegalStateTransitionException::class);
});

it('rejects reversed dates', function () {
    $registry = app(BlockRegistry::class);
    $action = new UpdateContentBlock($registry);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();
    $data = new UpdateContentBlockData(
        starts_at: Carbon::now()->addDay(),
        ends_at: Carbon::now()
    );

    expect(fn () => $action($block->id, $data, ['text' => 'x', 'tone' => 'info']))
        ->toThrow(ValidationException::class);
});

it('does not log activity or update user if identical data is submitted', function () {
    $registry = app(BlockRegistry::class);
    $action = new UpdateContentBlock($registry);

    $user = User::factory()->create();
    $payload = AnnouncementBarData::validateAndCreate(['text' => 'same', 'tone' => 'info'])->toArray();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'payload' => $payload,
        'updated_by' => null,
    ]);

    $data = new UpdateContentBlockData(null, null);

    $action(
        id: $block->id,
        data: $data,
        rawPayload: ['text' => 'same', 'tone' => 'info'],
        causer: $user
    );

    $block->refresh();
    expect($block->updated_by)->toBeNull();

    \Pest\Laravel\assertDatabaseMissing('activity_log', [
        'subject_type' => ContentBlock::class,
        'subject_id' => $block->id,
    ]);
});

it('re-saves with new version if schema_version is stale but payload is identical', function () {
    /** @var MockInterface&BlockDefinition $definition */
    $definition = Mockery::mock(BlockDefinition::class);
    $definition->shouldReceive('typeKey')->andReturn('stale_test_block');
    $definition->shouldReceive('type')->andReturn('stale_test_block');
    $definition->shouldReceive('schemaVersion')->andReturn(2); // new version
    $definition->shouldReceive('dataClass')->andReturn(AnnouncementBarData::class);

    $registry = new ContentBlockRegistry;
    $registry->register($definition);

    $action = new UpdateContentBlock($registry);
    $user = User::factory()->create();
    $payload = AnnouncementBarData::validateAndCreate(['text' => 'same', 'tone' => 'info'])->toArray();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'stale_test_block',
        'payload' => $payload,
        'schema_version' => 1, // stale
        'updated_by' => null,
    ]);

    $data = new UpdateContentBlockData(null, null);

    $action(
        id: $block->id,
        data: $data,
        rawPayload: ['text' => 'same', 'tone' => 'info'],
        causer: $user
    );

    $block->refresh();
    expect($block->updated_by)->toBe($user->id)
        ->and($block->schema_version)->toBe(2);
});
