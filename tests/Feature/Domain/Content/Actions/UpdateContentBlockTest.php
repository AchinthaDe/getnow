<?php

declare(strict_types=1);

use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Blocks\AnnouncementBar\AnnouncementBarData;
use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\PlacementCapacityExceededException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Exceptions\UnsupportedSchemaVersionException;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ContentBlockRegistry;
use App\Domain\Content\Services\EnsurePlacementCapacity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Spatie\Activitylog\Models\Activity;

it('updates block and logs activity', function () {
    $action = app(UpdateContentBlock::class);

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

    expect($updated->payload)->toBe(['text' => 'new text', 'secondary_text' => null, 'locale_text' => null, 'link_url' => null, 'tone' => 'success'])
        ->and($updated->updated_by)->toBe($user->id);

    \Pest\Laravel\assertDatabaseHas('activity_log', [
        'subject_type' => ContentBlock::class,
        'subject_id' => $block->id,
        'causer_id' => $user->id,
        'description' => 'updated',
    ]);
});

it('rejects archived blocks', function () {
    $action = app(UpdateContentBlock::class);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->archived()->create();
    $data = new UpdateContentBlockData(null, null);

    expect(fn () => $action($block->id, $data, ['text' => 'x', 'tone' => 'info']))
        ->toThrow(IllegalStateTransitionException::class);
});

it('rejects reversed dates', function () {
    $action = app(UpdateContentBlock::class);

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
    $action = app(UpdateContentBlock::class);

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

    $action = new UpdateContentBlock($registry, app(EnsurePlacementCapacity::class));
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
    $action = app(UpdateContentBlock::class);

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
    $action = app(UpdateContentBlock::class);

    // Create via factory first to get defaults
    $seededUpdatedAt = now()->subDay();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create(['updated_by' => null, 'updated_at' => $seededUpdatedAt]);

    // Overwrite payload using raw DB with reversed keys
    DB::update(
        "UPDATE content_blocks SET payload = '{\"tone\": \"info\", \"text\": \"hello\", \"link_url\": null, \"locale_text\": null, \"secondary_text\": null}'::jsonb WHERE id = ?",
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
    expect($block->updated_by)->toBeNull()
        ->and($block->updated_at->timestamp)->toBe($seededUpdatedAt->timestamp);
    \Pest\Laravel\assertDatabaseMissing('activity_log', [
        'subject_id' => $block->id,
    ]);
});

it('converts timezones to UTC during update and detects dirtiness properly', function () {
    $action = app(UpdateContentBlock::class);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'starts_at' => '2026-01-01 06:30:00',
        'ends_at' => null,
        'payload' => AnnouncementBarData::validateAndCreate(['text' => 'hello', 'tone' => 'info'])->toArray(),
    ]);
    $updatedAt = $block->updated_at;

    // Resubmit same instant in different timezone with microseconds (which should be truncated)
    Carbon::setTestNow(now()->addSecond());
    $data = new UpdateContentBlockData(
        starts_at: Carbon::parse('2026-01-01 10:00:00.999999', 'Asia/Colombo'), // 04:30 UTC
        ends_at: null,
    );
    $action($block->id, $data, ['text' => 'hello', 'link_url' => null, 'tone' => 'info'], null);

    $rawStartsAtEpoch = DB::selectOne('SELECT extract(epoch from starts_at) as epoch FROM content_blocks WHERE id = ?', [$block->id])->epoch;
    expect($rawStartsAtEpoch)->toEqual(Carbon::parse('2026-01-01 04:30:00', 'UTC')->timestamp);

    $block->refresh();
    expect($block->updated_at->timestamp)->not->toBe($updatedAt->timestamp);

    $updatedAt = $block->updated_at;

    // Resubmit same instant again, should not update
    Carbon::setTestNow(now()->addSecond());
    $action($block->id, clone $data, ['text' => 'hello', 'link_url' => null, 'tone' => 'info'], null);
    expect($block->refresh()->updated_at->timestamp)->toBe($updatedAt->timestamp);

    // Verify only one activity log was written for this block
    expect(Activity::where('subject_id', $block->id)->count())->toBe(1);

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

    $action = new UpdateContentBlock($registry, app(EnsurePlacementCapacity::class));
    $payload = AnnouncementBarData::validateAndCreate(['text' => 'same', 'tone' => 'info'])->toArray();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'downgrade_test_block',
        'payload' => $payload,
        'schema_version' => 2, // stored version is higher
    ]);

    $data = new UpdateContentBlockData(null, null);

    expect(fn () => $action($block->id, $data, ['text' => 'new'], null))
        ->toThrow(UnsupportedSchemaVersionException::class);
});

it('remaps payload validation errors correctly', function () {
    $action = app(UpdateContentBlock::class);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    $data = new UpdateContentBlockData(null, null);

    $e = null;
    try {
        $action($block->id, $data, ['text' => '', 'tone' => 'info']);
    } catch (ValidationException $caught) {
        $e = $caught;
    }
    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and($e->errors())->toHaveKey('payload.text');

    $e2 = null;
    try {
        $action($block->id, $data, ['text' => 'hello', 'link_url' => 'javascript:alert(1)', 'tone' => 'info']);
    } catch (ValidationException $caught) {
        $e2 = $caught;
    }
    expect($e2)->toBeInstanceOf(ValidationException::class)
        ->and($e2->errors())->toHaveKey('payload.link_url');
});

it('logs specific changed properties', function () {
    $registry = app(BlockRegistry::class);
    $action = new UpdateContentBlock($registry, app(EnsurePlacementCapacity::class));
    $user = User::factory()->create();

    // 1. Payload only
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create(['payload' => AnnouncementBarData::validateAndCreate(['text' => 'old', 'tone' => 'info'])->toArray(), 'starts_at' => null, 'ends_at' => null]);
    $data = new UpdateContentBlockData(null, null);
    $action($block->id, $data, ['text' => 'new', 'tone' => 'info'], $user);
    $log = Activity::where('subject_id', $block->id)->latest('id')->first();
    expect($log->properties['changed'])->toBe(['payload']);

    // 2. Starts_at only
    /** @var ContentBlock $block2 */
    $block2 = ContentBlock::factory()->create(['payload' => AnnouncementBarData::validateAndCreate(['text' => 'same', 'tone' => 'info'])->toArray(), 'starts_at' => null, 'ends_at' => null]);
    $data2 = new UpdateContentBlockData(Carbon::now(), null);
    $action($block2->id, $data2, ['text' => 'same', 'tone' => 'info'], $user);
    $log2 = Activity::where('subject_id', $block2->id)->latest('id')->first();
    expect($log2->properties['changed'])->toBe(['starts_at']);

    // 3. Schema version
    /** @var MockInterface&BlockDefinition $definition */
    $definition = Mockery::mock(BlockDefinition::class);
    $definition->shouldReceive('typeKey')->andReturn('stale_log_block');
    $definition->shouldReceive('type')->andReturn('stale_log_block');
    $definition->shouldReceive('schemaVersion')->andReturn(2);
    $definition->shouldReceive('dataClass')->andReturn(AnnouncementBarData::class);
    $registry->register($definition);

    /** @var ContentBlock $block3 */
    $block3 = ContentBlock::factory()->create(['type' => 'stale_log_block', 'payload' => AnnouncementBarData::validateAndCreate(['text' => 'same', 'tone' => 'info'])->toArray(), 'schema_version' => 1]);
    $data3 = new UpdateContentBlockData(null, null);
    $action($block3->id, $data3, ['text' => 'same', 'tone' => 'info'], $user);
    $log3 = Activity::where('subject_id', $block3->id)->latest('id')->first();
    expect($log3->properties['changed'])->toBe(['schema_version']);
});

it('rejects sort_order outside valid integer range [0, 2147483647]', function () {
    $action = app(UpdateContentBlock::class);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    expect(fn () => $action(
        $block->id,
        new UpdateContentBlockData(null, null, -1),
        ['text' => 'hello', 'tone' => 'info']
    ))->toThrow(ValidationException::class);

    expect(fn () => $action(
        $block->id,
        new UpdateContentBlockData(null, null, 2147483648),
        ['text' => 'hello', 'tone' => 'info']
    ))->toThrow(ValidationException::class);
});

it('accepts sort_order at valid boundary values 0 and 2147483647', function () {
    $action = app(UpdateContentBlock::class);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    $action(
        $block->id,
        new UpdateContentBlockData(null, null, ContentBlock::MIN_SORT_ORDER),
        ['text' => 'hello', 'tone' => 'info']
    );
    expect($block->fresh()->sort_order)->toBe(ContentBlock::MIN_SORT_ORDER);

    $action(
        $block->id,
        new UpdateContentBlockData(null, null, ContentBlock::MAX_SORT_ORDER),
        ['text' => 'hello', 'tone' => 'info']
    );
    expect($block->fresh()->sort_order)->toBe(ContentBlock::MAX_SORT_ORDER);
});

it('allows text-only edit on over-capacity legacy block without throwing capacity exception', function () {
    $action = app(UpdateContentBlock::class);

    /** @var ContentBlock $block1 */
    $block1 = ContentBlock::factory()->published()->create([
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'is_enabled' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);
    /** @var ContentBlock $block2 */
    $block2 = ContentBlock::factory()->published()->create([
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'is_enabled' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $updated = $action(
        id: $block2->id,
        data: new UpdateContentBlockData(starts_at: null, ends_at: null),
        rawPayload: ['text' => 'Updated Text Without Schedule Change', 'tone' => 'info'],
    );

    expect($updated->payload['text'])->toBe('Updated Text Without Schedule Change');
});

it('throws capacity exception when schedule edit overlaps existing active singleton block', function () {
    $action = app(UpdateContentBlock::class);

    /** @var ContentBlock $block1 */
    $block1 = ContentBlock::factory()->published()->create([
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'is_enabled' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $futureStart = now()->addDays(7);
    /** @var ContentBlock $block2 */
    $block2 = ContentBlock::factory()->published()->create([
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'is_enabled' => true,
        'starts_at' => $futureStart,
        'ends_at' => null,
    ]);

    expect(fn () => $action(
        id: $block2->id,
        data: new UpdateContentBlockData(starts_at: now(), ends_at: null),
        rawPayload: ['text' => 'Overlapping schedule edit', 'tone' => 'info'],
    ))->toThrow(PlacementCapacityExceededException::class);
});

it('passes when updating an over-capacity legacy block with identical non-null schedule', function () {
    $action = app(UpdateContentBlock::class);

    $startsAt = Carbon::parse('2026-01-01 10:00:00');
    $endsAt = Carbon::parse('2026-12-31 23:59:59');

    /** @var ContentBlock $block1 */
    $block1 = ContentBlock::factory()->published()->create([
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'is_enabled' => true,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);
    /** @var ContentBlock $block2 */
    $block2 = ContentBlock::factory()->published()->create([
        'placement' => 'announcement_bar',
        'type' => 'announcement_bar',
        'is_enabled' => true,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);

    $updated = $action(
        id: $block2->id,
        data: new UpdateContentBlockData(starts_at: $startsAt, ends_at: $endsAt),
        rawPayload: ['text' => 'Same schedule update', 'tone' => 'info'],
    );

    expect($updated->payload['text'])->toBe('Same schedule update');
});
