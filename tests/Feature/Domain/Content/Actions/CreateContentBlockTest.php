<?php

declare(strict_types=1);

use App\Domain\Content\Actions\CreateContentBlock;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\CreateContentBlockData;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Models\ContentBlock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

it('creates block and logs activity', function () {
    $registry = app(BlockRegistry::class);
    $action = new CreateContentBlock($registry);

    $user = User::factory()->create();

    $data = new CreateContentBlockData(
        placement: Placement::ANNOUNCEMENT_BAR,
        type: 'announcement_bar',
        starts_at: null,
        ends_at: null,
    );

    $block = $action(
        data: $data,
        rawPayload: ['text' => 'hello', 'tone' => 'info'],
        causer: $user
    );

    expect($block->status)->toBe(PublishStatus::DRAFT)
        ->and($block->payload)->toBe(['text' => 'hello', 'link_url' => null, 'tone' => 'info'])
        ->and($block->created_by)->toBe($user->id)
        ->and($block->schema_version)->toBe(1);

    \Pest\Laravel\assertDatabaseHas('activity_log', [
        'subject_type' => ContentBlock::class,
        'subject_id' => $block->id,
        'causer_id' => $user->id,
        'description' => 'created',
    ]);
});

it('rejects unknown type', function () {
    $registry = app(BlockRegistry::class);
    $action = new CreateContentBlock($registry);

    $data = new CreateContentBlockData(
        placement: Placement::ANNOUNCEMENT_BAR,
        type: 'unknown_type',
        starts_at: null,
        ends_at: null,
    );

    expect(fn () => $action($data, ['text' => 'hello', 'tone' => 'info']))
        ->toThrow(UnknownBlockTypeException::class);
});

it('rejects reversed dates', function () {
    $registry = app(BlockRegistry::class);
    $action = new CreateContentBlock($registry);

    $data = new CreateContentBlockData(
        placement: Placement::ANNOUNCEMENT_BAR,
        type: 'announcement_bar',
        starts_at: Carbon::now()->addDay(),
        ends_at: Carbon::now(),
    );

    expect(fn () => $action($data, ['text' => 'x', 'tone' => 'info']))
        ->toThrow(ValidationException::class);
});

it('remaps payload validation errors', function () {
    $registry = app(BlockRegistry::class);
    $action = new CreateContentBlock($registry);

    $data = new CreateContentBlockData(
        placement: Placement::ANNOUNCEMENT_BAR,
        type: 'announcement_bar',
        starts_at: null,
        ends_at: null,
    );

    $e = null;
    try {
        $action($data, ['text' => '', 'link_url' => 'invalid'], null);
    } catch (ValidationException $caught) {
        $e = $caught;
    }
    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and($e->errors())->toHaveKey('payload.text')
        ->and($e->errors())->toHaveKey('payload.link_url');
});

it('normalizes tone to lowercase and scheme to lowercase in db', function () {
    $registry = app(BlockRegistry::class);
    $action = new CreateContentBlock($registry);

    $data = new CreateContentBlockData(
        placement: Placement::ANNOUNCEMENT_BAR,
        type: 'announcement_bar',
        starts_at: null,
        ends_at: null,
    );

    $block = $action($data, ['text' => 'hello', 'link_url' => 'HTTPS://x.com/Path', 'tone' => 'info'], null);

    $raw = DB::table('content_blocks')->where('id', $block->id)->first();
    $payload = json_decode($raw->payload, true);

    expect($payload['tone'])->toBe('info')
        ->and($payload['link_url'])->toBe('https://x.com/Path');
});

it('converts timezones to UTC', function () {
    $registry = app(BlockRegistry::class);
    $action = new CreateContentBlock($registry);

    $data = new CreateContentBlockData(
        placement: Placement::ANNOUNCEMENT_BAR,
        type: 'announcement_bar',
        starts_at: Carbon::parse('2026-01-01 12:00:00', 'Asia/Colombo'),
        ends_at: null,
    );

    $block = $action($data, ['text' => 'hello', 'tone' => 'info'], null);

    $raw = DB::table('content_blocks')->where('id', $block->id)->first();
    expect(Carbon::parse($raw->starts_at)->format('H:i:s'))->toBe('06:30:00');
});
