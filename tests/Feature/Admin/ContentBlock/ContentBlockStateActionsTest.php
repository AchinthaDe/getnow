<?php

declare(strict_types=1);

use App\Domain\Content\Actions\ArchiveContentBlock;
use App\Domain\Content\Actions\DisableContentBlock;
use App\Domain\Content\Actions\EnableContentBlock;
use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Actions\ContentBlockTransitionActions;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\EditContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ListContentBlocks;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ViewContentBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TestBlocks;

uses(RefreshDatabase::class);

beforeEach(function () {
    TestBlocks::register();
});

// A1: Authorization tests
it('hides all transition actions from non-admins', function () {
    /** @var User $user */
    $user = User::factory()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    \Pest\Laravel\actingAs($user);

    $actions = [
        ContentBlockTransitionActions::publish()->record($block),
        ContentBlockTransitionActions::archive()->record($block),
        ContentBlockTransitionActions::enable()->record($block),
        ContentBlockTransitionActions::disable()->record($block),
    ];

    foreach ($actions as $action) {
        expect($action->isHidden())->toBeTrue();
    }
});

it('aborts with 403 when non-admin executes transition action directly', function () {
    /** @var User $user */
    $user = User::factory()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    \Pest\Laravel\actingAs($user);

    $action = ContentBlockTransitionActions::publish();
    $action->record($block);

    $threw = false;
    try {
        $action->call();
    } catch (HttpException $e) {
        $threw = true;
        expect($e->getStatusCode())->toBe(403);
    }
    expect($threw)->toBeTrue();

    $block->refresh();
    expect($block->status)->toBe(PublishStatus::DRAFT);
    expect(Activity::forSubject($block)->count())->toBe(0);
});

dataset('stateLegalityMatrix', [
    'draft + enabled' => [
        PublishStatus::DRAFT,
        true,
        ['edit', 'publish', 'archive'],
        ['enable', 'disable'],
    ],
    'draft + disabled' => [
        PublishStatus::DRAFT,
        false,
        ['edit', 'publish', 'archive'],
        ['enable', 'disable'],
    ],
    'published + enabled' => [
        PublishStatus::PUBLISHED,
        true,
        ['edit', 'disable', 'archive'],
        ['publish', 'enable'],
    ],
    'published + disabled' => [
        PublishStatus::PUBLISHED,
        false,
        ['edit', 'enable', 'archive'],
        ['publish', 'disable'],
    ],
    'archived + disabled' => [
        PublishStatus::ARCHIVED,
        false,
        [],
        ['edit', 'publish', 'archive', 'enable', 'disable'],
    ],
    'archived + enabled (tamper)' => [
        PublishStatus::ARCHIVED,
        true,
        [],
        ['edit', 'publish', 'archive', 'enable', 'disable'],
    ],
]);

// AC-8: Visibility matrix dataset test for View header actions
it('renders visible header actions matching exact matrix across all statuses and enabled states on view page', function (
    PublishStatus $status,
    bool $isEnabled,
    array $visibleActions,
    array $hiddenActions
) {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => $status,
        'is_enabled' => $isEnabled,
    ]);

    $view = Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $block->public_id]);

    foreach ($visibleActions as $action) {
        $view->assertActionVisible($action);
    }
    foreach ($hiddenActions as $action) {
        $view->assertActionHidden($action);
    }
})->with('stateLegalityMatrix');

// AC-8: Visibility matrix dataset test for List table row actions
it('renders visible table row actions matching exact matrix across all statuses and enabled states on list page', function (
    PublishStatus $status,
    bool $isEnabled,
    array $visibleActions,
    array $hiddenActions
) {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => $status,
        'is_enabled' => $isEnabled,
    ]);

    $list = Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->set('tableFilters.hide_archived.isActive', false);

    foreach ($visibleActions as $action) {
        $list->assertTableActionVisible($action, record: $block);
    }
    foreach ($hiddenActions as $action) {
        $list->assertTableActionHidden($action, record: $block);
    }
})->with('stateLegalityMatrix');

// m2: Verify no visible action would throw IllegalStateTransitionException
it('verifies no visible transition action throws IllegalStateTransitionException', function (
    PublishStatus $status,
    bool $isEnabled,
    array $visibleActions
) {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'status' => $status,
        'is_enabled' => $isEnabled,
    ]);

    foreach ($visibleActions as $action) {
        if ($action === 'edit') {
            continue;
        }

        $freshBlock = $block->fresh();
        assert($freshBlock instanceof ContentBlock);

        match ($action) {
            'publish' => app(PublishContentBlock::class)((int) $freshBlock->id, $admin),
            'archive' => app(ArchiveContentBlock::class)((int) $freshBlock->id, $admin),
            'enable' => app(EnableContentBlock::class)((int) $freshBlock->id, $admin),
            'disable' => app(DisableContentBlock::class)((int) $freshBlock->id, $admin),
            default => null,
        };
    }

    expect(true)->toBeTrue();
})->with('stateLegalityMatrix');

it('forbids non-admin from list and view routes', function () {
    /** @var User $user */
    $user = User::factory()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    \Pest\Laravel\actingAs($user)
        ->get(ContentBlockResource::getUrl('index'))
        ->assertForbidden();

    \Pest\Laravel\actingAs($user)
        ->get(ContentBlockResource::getUrl('view', ['record' => $block]))
        ->assertForbidden();
});

// AC-9: Publish Action with confirmation modal, activity log, and m3 assertions
it('publishes draft block from view header and table row with confirmation modal and activity log', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $blockHeader */
    $blockHeader = ContentBlock::factory()->draft()->create();

    $view = Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $blockHeader->public_id]);

    $view->mountAction('publish')
        ->assertActionMounted('publish')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Content Block published');

    $blockHeader->refresh();
    expect($blockHeader->status)->toBe(PublishStatus::PUBLISHED);
    expect(Activity::forSubject($blockHeader)->where('event', 'published')->count())->toBe(1);

    /** @var Activity $activity */
    $activity = Activity::forSubject($blockHeader)->where('event', 'published')->first();
    expect($activity->causer_id)->toBe($admin->id);
    expect($activity->properties->toArray())->toBe([
        'old_status' => 'draft',
        'new_status' => 'published',
    ]);

    // m3: updates without reload
    $view->assertActionHidden('publish')
        ->assertActionVisible('disable');

    // m3: refreshed record
    /** @var ViewContentBlock $viewInstance */
    $viewInstance = $view->instance();
    /** @var ContentBlock $recordInstance */
    $recordInstance = $viewInstance->getRecord();
    expect($recordInstance->status)->toBe(PublishStatus::PUBLISHED);

    // Table row publish
    /** @var ContentBlock $blockRow */
    $blockRow = ContentBlock::factory()->draft()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->callTableAction('publish', record: $blockRow)
        ->assertHasNoTableActionErrors()
        ->assertTableColumnStateSet('status', PublishStatus::PUBLISHED, record: $blockRow)
        ->assertNotified('Content Block published');

    $blockRow->refresh();
    expect($blockRow->status)->toBe(PublishStatus::PUBLISHED);
    expect(Activity::forSubject($blockRow)->where('event', 'published')->count())->toBe(1);
});

// AC-10: Unservable payload rejection on table row surface
it('rejects publish and enable on unservable block payload without writing activity log', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    // Draft with unservable payload (Publish attempt)
    /** @var ContentBlock $draftBlock */
    $draftBlock = ContentBlock::factory()->draft()->invalidAfterUpgrade()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->callTableAction('publish', record: $draftBlock)
        ->assertNotified('Publish Failed');

    $draftBlock->refresh();
    expect($draftBlock->status)->toBe(PublishStatus::DRAFT);
    expect(Activity::forSubject($draftBlock)->count())->toBe(0);

    // Disabled published with unservable payload (Enable attempt)
    /** @var ContentBlock $disabledBlock */
    $disabledBlock = ContentBlock::factory()->published()->disabled()->invalidAfterUpgrade()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->callTableAction('enable', record: $disabledBlock)
        ->assertNotified('Enable Failed');

    $disabledBlock->refresh();
    expect($disabledBlock->is_enabled)->toBeFalse();
    expect(Activity::forSubject($disabledBlock)->count())->toBe(0);
});

// AC-11: Archive Action on draft and published blocks
it('archives block from view header and table row and updates visible actions', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    // View header archive on draft block
    /** @var ContentBlock $draftBlock */
    $draftBlock = ContentBlock::factory()->draft()->create();

    $view = Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $draftBlock->public_id]);

    $view->mountAction('archive')
        ->assertActionMounted('archive')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Content Block archived');

    $draftBlock->refresh();
    expect($draftBlock->status)->toBe(PublishStatus::ARCHIVED);
    expect($draftBlock->is_enabled)->toBeFalse();
    expect(Activity::forSubject($draftBlock)->where('event', 'archived')->count())->toBe(1);

    /** @var Activity $draftActivity */
    $draftActivity = Activity::forSubject($draftBlock)->where('event', 'archived')->first();
    expect($draftActivity->causer_id)->toBe($admin->id);
    expect($draftActivity->properties->toArray())->toBe([
        'old_status' => 'draft',
        'new_status' => 'archived',
        'old_is_enabled' => true,
        'new_is_enabled' => false,
    ]);

    // All transition actions and edit action are now hidden
    $view->assertActionHidden('publish')
        ->assertActionHidden('archive')
        ->assertActionHidden('enable')
        ->assertActionHidden('disable')
        ->assertActionHidden('edit');

    // Table row archive on published block
    /** @var ContentBlock $publishedBlock */
    $publishedBlock = ContentBlock::factory()->published()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->callTableAction('archive', record: $publishedBlock)
        ->assertHasNoTableActionErrors()
        ->assertNotified('Content Block archived');

    $publishedBlock->refresh();
    expect($publishedBlock->status)->toBe(PublishStatus::ARCHIVED);
    expect($publishedBlock->is_enabled)->toBeFalse();
    expect(Activity::forSubject($publishedBlock)->where('event', 'archived')->count())->toBe(1);

    /** @var Activity $pubActivity */
    $pubActivity = Activity::forSubject($publishedBlock)->where('event', 'archived')->first();
    expect($pubActivity->causer_id)->toBe($admin->id);
    expect($pubActivity->properties->toArray())->toBe([
        'old_status' => 'published',
        'new_status' => 'archived',
        'old_is_enabled' => true,
        'new_is_enabled' => false,
    ]);
});

// AC-12: Enable and Disable transitions on View header
it('enables and disables published block from view header', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    // Disable on View header
    /** @var ContentBlock $enabledBlock */
    $enabledBlock = ContentBlock::factory()->published()->create();

    $view = Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $enabledBlock->public_id]);

    $view->callAction('disable')
        ->assertHasNoActionErrors()
        ->assertNotified('Content Block disabled');

    $enabledBlock->refresh();
    expect($enabledBlock->is_enabled)->toBeFalse();
    expect(Activity::forSubject($enabledBlock)->where('event', 'disabled')->count())->toBe(1);

    /** @var Activity $disableActivity */
    $disableActivity = Activity::forSubject($enabledBlock)->where('event', 'disabled')->first();
    expect($disableActivity->causer_id)->toBe($admin->id);
    expect($disableActivity->properties->toArray())->toBe([
        'old_is_enabled' => true,
        'new_is_enabled' => false,
    ]);

    $view->assertActionVisible('enable')
        ->assertActionHidden('disable');

    // Enable on View header
    $view->mountAction('enable')
        ->assertActionMounted('enable')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Content Block enabled');

    $enabledBlock->refresh();
    expect($enabledBlock->is_enabled)->toBeTrue();
    expect(Activity::forSubject($enabledBlock)->where('event', 'enabled')->count())->toBe(1);

    /** @var Activity $enableActivity */
    $enableActivity = Activity::forSubject($enabledBlock)->where('event', 'enabled')->first();
    expect($enableActivity->causer_id)->toBe($admin->id);
    expect($enableActivity->properties->toArray())->toBe([
        'old_is_enabled' => false,
        'new_is_enabled' => true,
    ]);

    $view->assertActionVisible('disable')
        ->assertActionHidden('enable');
});

// AC-12: Archive and Disable on table row for broken payload (brief trap 5)
it('archives and disables broken payload block from table row without hydration', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    // Broken payload table row archive
    /** @var ContentBlock $brokenDraft */
    $brokenDraft = ContentBlock::factory()->draft()->failedUpgrade()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->callTableAction('archive', record: $brokenDraft)
        ->assertHasNoTableActionErrors()
        ->assertNotified('Content Block archived');

    $brokenDraft->refresh();
    expect($brokenDraft->status)->toBe(PublishStatus::ARCHIVED);
    expect(Activity::forSubject($brokenDraft)->where('event', 'archived')->count())->toBe(1);

    // Broken payload table row disable
    /** @var ContentBlock $brokenPublished */
    $brokenPublished = ContentBlock::factory()->published()->enabled()->failedUpgrade()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->callTableAction('disable', record: $brokenPublished)
        ->assertHasNoTableActionErrors()
        ->assertNotified('Content Block disabled');

    $brokenPublished->refresh();
    expect($brokenPublished->is_enabled)->toBeFalse();
    expect(Activity::forSubject($brokenPublished)->where('event', 'disabled')->count())->toBe(1);
});

// AC-13: Stale state handling, idempotency & direct calls
it('handles stale state transition gracefully with danger notification and record refresh', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    app()->bind(PublishContentBlock::class, fn () => new class
    {
        public function __invoke(int $id, ?User $causer = null): ContentBlock
        {
            throw new IllegalStateTransitionException('Cannot publish an archived block.');
        }
    });

    $view = Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $block->public_id]);

    $view->mountAction('publish')
        ->callMountedAction()
        ->assertNotified('Action Failed');

    $block->refresh();
    expect($block->status)->toBe(PublishStatus::DRAFT);
    expect(Activity::forSubject($block)->count())->toBe(0);
});

it('probes modal race condition when record archived concurrently', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    $component = Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $block->public_id])
        ->mountAction('publish');

    // Archive concurrently behind the mounted modal
    DB::table('content_blocks')->where('id', $block->id)->update(['status' => 'archived']);

    $component->callMountedAction()
        ->assertSuccessful();

    $block->refresh();
    expect($block->status)->toBe(PublishStatus::ARCHIVED);
    expect(Activity::forSubject($block)->where('event', 'published')->count())->toBe(0);
});

it('probes tab B disable click after tab A archives block', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->enabled()->create();

    $tabB = Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $block->public_id]);

    // Tab A concurrently archives
    DB::table('content_blocks')->where('id', $block->id)->update([
        'status' => 'archived',
        'is_enabled' => false,
    ]);

    $tabB->callAction('disable')
        ->assertSuccessful()
        ->assertActionHidden('disable')
        ->assertActionHidden('enable')
        ->assertActionHidden('publish')
        ->assertActionHidden('archive')
        ->assertActionHidden('edit');

    // And verify the database state remains unchanged by tab B:
    $block->refresh();
    expect($block->status)->toBe(PublishStatus::ARCHIVED);
    expect($block->is_enabled)->toBeFalse();
    expect(Activity::forSubject($block)->where('event', 'disabled')->count())->toBe(0);
});

it('succeeds idempotently without duplicate activity log when concurrent transition occurs', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    // Stub simulating idempotent return of unchanged record without activity log
    app()->bind(PublishContentBlock::class, fn () => new class
    {
        public function __invoke(int $id, ?User $causer = null): ContentBlock
        {
            /** @var ContentBlock $found */
            $found = ContentBlock::query()->findOrFail($id);

            return $found;
        }
    });

    Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $block->public_id])
        ->callAction('publish')
        ->assertHasNoActionErrors()
        ->assertNotified('Content Block published');

    expect(Activity::forSubject($block)->count())->toBe(0);
});

// m1: Direct call on hidden action throws exact vendor exception class
it('throws AssertionFailedError when calling hidden action directly', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->archived()->create();

    expect(fn () => Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $block->public_id])
        ->callAction('publish')
    )->toThrow(AssertionFailedError::class);
});

// M6: Timezone display parity between edit and view
it('renders identical wall time in view page as edit page for configured admin timezone', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    config(['admin.timezone' => 'Asia/Colombo']);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create([
        'starts_at' => Carbon::parse('2026-10-15 03:30:00', 'UTC'),
    ]);

    Livewire::actingAs($admin)
        ->test(EditContentBlock::class, ['record' => $block->public_id])
        ->assertSet('data.starts_at', '2026-10-15 09:00:00');

    Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $block->public_id])
        ->assertSeeText('2026-10-15 09:00:00');
});
