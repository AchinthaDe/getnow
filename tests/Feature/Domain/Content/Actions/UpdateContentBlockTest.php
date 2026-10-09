<?php

declare(strict_types=1);

use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarData;
use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ContentBlockRegistry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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

it('throws UnknownBlockTypeException for unregistered type', function () {
    $registry = app(BlockRegistry::class);
    $action = new UpdateContentBlock($registry);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'some_unknown_type',
        'schema_version' => 1,
    ]);

    $data = new UpdateContentBlockData(null, null);

    expect(fn () => $action($block->id, $data, ['text' => 'new']))
        ->toThrow(UnknownBlockTypeException::class);
});

it('writes nothing if identical data resubmitted but jsonb order differs', function () {
    $registry = app(BlockRegistry::class);
    $action = new UpdateContentBlock($registry);

    // Create via factory first to get defaults
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create(['updated_by' => null, 'updated_at' => now()->subDay()]);

    // Overwrite payload using raw DB with reversed keys
    DB::update(
        "UPDATE content_blocks SET payload = '{\"tone\": \"info\", \"text\": \"hello\", \"link_url\": null}'::jsonb WHERE id = ?",
        [$block->id]
    );

    $data = new UpdateContentBlockData(null, null);

    $action(
        id: $block->id,
        data: $data,
        rawPayload: ['text' => 'hello', 'tone' => 'info'],
        causer: null
    );

    $block->refresh();
    expect($block->updated_by)->toBeNull();
    \Pest\Laravel\assertDatabaseMissing('activity_log', [
        'subject_id' => $block->id,
    ]);
});

it('converts timezones to UTC during update and detects dirtiness properly', function () {
    $registry = app(BlockRegistry::class);
    $action = new UpdateContentBlock($registry);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'starts_at' => '2026-01-01 06:30:00',
        'ends_at' => null,
        'payload' => AnnouncementBarData::validateAndCreate(['text' => 'hello', 'tone' => 'info'])->toArray(),
    ]);
    $updatedAt = $block->updated_at;

    // Resubmit same instant in different timezone
    $data = new UpdateContentBlockData(
        starts_at: Carbon::parse('2026-01-01 10:00:00', 'Asia/Colombo'), // 04:30 UTC
        ends_at: null,
    );
    $action($block->id, $data, ['text' => 'hello', 'link_url' => null, 'tone' => 'info'], null);

    $rawStartsAtEpoch = DB::selectOne("SELECT extract(epoch from starts_at) as epoch FROM content_blocks WHERE id = ?", [$block->id])->epoch;
    expect($rawStartsAtEpoch)->toEqual(Carbon::parse('2026-01-01 04:30:00', 'UTC')->timestamp);

    $block->refresh();
    expect($block->updated_at->timestamp)->not->toBe($updatedAt->timestamp);

    $updatedAt = $block->updated_at;

    // Resubmit same instant again, should not update
    $action($block->id, clone $data, ['text' => 'hello', 'link_url' => null, 'tone' => 'info'], null);
    expect($block->refresh()->updated_at->timestamp)->toBe($updatedAt->timestamp);

    // Resubmit DIFFERENT instant
    Carbon::setTestNow(now()->addSecond());
    $data2 = new UpdateContentBlockData(
        starts_at: Carbon::parse('2026-01-01 12:00:01', 'Asia/Colombo'),
        ends_at: null,
    );
    $action($block->id, $data2, ['text' => 'hello', 'link_url' => null, 'tone' => 'info'], null);

    expect($block->refresh()->updated_at->timestamp)->not->toBe($updatedAt->timestamp);
});

it('throws UnsupportedSchemaVersionException if stored version is higher than code version', function () {
    /** @var MockInterface&BlockDefinition $definition */
    $definition = Mockery::mock(BlockDefinition::class);
    $definition->shouldReceive('typeKey')->andReturn('downgrade_test_block');
    $definition->shouldReceive('type')->andReturn('downgrade_test_block');
    $definition->shouldReceive('schemaVersion')->andReturn(1); // code version
    $definition->shouldReceive('dataClass')->andReturn(AnnouncementBarData::class);

    $registry = new ContentBlockRegistry;
    $registry->register($definition);

    $action = new UpdateContentBlock($registry);
    $payload = AnnouncementBarData::validateAndCreate(['text' => 'same', 'tone' => 'info'])->toArray();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'downgrade_test_block',
        'payload' => $payload,
        'schema_version' => 2, // stored version is higher
    ]);

    $data = new UpdateContentBlockData(null, null);

    expect(fn () => $action($block->id, $data, ['text' => 'new'], null))
        ->toThrow(\App\Domain\Content\Exceptions\UnsupportedSchemaVersionException::class);
});
