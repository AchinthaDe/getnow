<?php

declare(strict_types=1);

use App\Domain\Content\Actions\ArchiveContentBlock;
use App\Domain\Content\Actions\CreateContentBlock;
use App\Domain\Content\Actions\DisableContentBlock;
use App\Domain\Content\Actions\EnableContentBlock;
use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\CreateContentBlockData;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Models\ContentBlock;
use Illuminate\Support\Facades\App;
use Spatie\Activitylog\Models\Activity;

dataset('actions', [
    'create' => [
        function () {
            $registry = app(BlockRegistry::class);
            $action = new CreateContentBlock($registry);
            $data = new CreateContentBlockData(
                type: 'announcement_bar',
                placement: Placement::ANNOUNCEMENT_BAR,
                starts_at: null,
                ends_at: null,
            );
            $action($data, ['text' => 'new', 'tone' => 'info'], null);
        },
        'created',
    ],
    'update' => [
        function () {
            $registry = app(BlockRegistry::class);
            $action = new UpdateContentBlock($registry);
            /** @var ContentBlock $block */
            $block = ContentBlock::factory()->create(['payload' => ['text' => 'old', 'tone' => 'info']]);
            $data = new UpdateContentBlockData(starts_at: null, ends_at: null);
            $action($block->id, $data, ['text' => 'new', 'tone' => 'info'], null);
        },
        'updated',
    ],
    'publish' => [
        function () {
            $action = App::make(PublishContentBlock::class);
            /** @var ContentBlock $block */
            $block = ContentBlock::factory()->draft()->create(['payload' => ['text' => 'valid', 'tone' => 'info']]);
            $action($block->id, null);
        },
        'published',
    ],
    'archive' => [
        function () {
            $action = new ArchiveContentBlock;
            /** @var ContentBlock $block */
            $block = ContentBlock::factory()->create();
            $action($block->id, null);
        },
        'archived',
    ],
    'enable' => [
        function () {
            $action = App::make(EnableContentBlock::class);
            /** @var ContentBlock $block */
            $block = ContentBlock::factory()->disabled()->create();
            $action($block->id, null);
        },
        'enabled',
    ],
    'disable' => [
        function () {
            $action = new DisableContentBlock;
            /** @var ContentBlock $block */
            $block = ContentBlock::factory()->create(['is_enabled' => true]);
            $action($block->id, null);
        },
        'disabled',
    ],
]);

it('writes exactly one activity log with null causer for real changes', function (Closure $runAction, string $expectedEvent) {
    $initialCount = Activity::count();

    $runAction();

    expect(Activity::count())->toBe($initialCount + 1);

    $log = Activity::latest('id')->first();
    expect($log->event)->toBe($expectedEvent)
        ->and($log->causer_id)->toBeNull();
})->with('actions');
