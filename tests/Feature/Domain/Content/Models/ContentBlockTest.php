<?php

declare(strict_types=1);

use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('hydrates unknown placement without crashing', function () {
    $id = (string) Str::ulid();
    DB::table('content_blocks')->insert([
        'public_id' => $id,
        'placement' => 'deleted_placement',
        'type' => 'announcement_bar',
        'schema_version' => 1,
        'payload' => '{}',
        'status' => 'draft',
        'is_enabled' => true,
        'sort_order' => 0,
    ]);

    $block = ContentBlock::where('public_id', $id)->firstOrFail();

    expect($block->placement)->toBeNull()
        ->and($block->getRawOriginal('placement'))->toBe('deleted_placement');
});

it('does not call registry or upgrader in isLive', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => 'published',
        'is_enabled' => true,
    ]);

    $mockRegistry = Mockery::mock(BlockRegistry::class);
    $mockRegistry->shouldNotReceive('get');
    app()->instance(BlockRegistry::class, $mockRegistry);

    app()->bind(ResolveServablePayload::class, function () {
        throw new Exception('Resolver should not be called');
    });

    expect($block->isLive())->toBeTrue();
});
