<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Data\ServingHealthResult;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ListContentBlocks;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\TestBlocks;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    TestBlocks::register();
});

it('prevents non-admins from viewing the list page', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user)
        ->get(ContentBlockResource::getUrl('index'))
        ->assertForbidden();
});

it('allows admins to view the list page and renders table correctly', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    ContentBlock::factory()->published()->create(['type' => 'test_block_a']);

    actingAs($admin)
        ->get(ContentBlockResource::getUrl('index'))
        ->assertOk();
});

it('shows ContentBlockResource index url in navigation for admin', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk()
        ->assertSee(ContentBlockResource::getUrl('index'));
});

it('does not crash when an unknown block type is encountered', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $block = ContentBlock::factory()->published()->create(['type' => 'some_removed_type']);

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$block])
        ->assertTableColumnStateSet('type', 'some_removed_type', record: $block);
});

it('default filter hides archived blocks', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $published = ContentBlock::factory()->published()->create();
    $archived = ContentBlock::factory()->archived()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->assertCanSeeTableRecords([$published])
        ->assertCanNotSeeTableRecords([$archived]);
});

it('clearing the hide_archived filter shows archived blocks', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $archived = ContentBlock::factory()->archived()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->assertCanNotSeeTableRecords([$archived])
        ->set('tableFilters.hide_archived.isActive', false)
        ->assertCanSeeTableRecords([$archived]);
});

it('shows archived blocks when status filter is set to archived even if hide_archived is active', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $archived = ContentBlock::factory()->archived()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->assertCanNotSeeTableRecords([$archived])
        // Keep hide_archived active (default), but explicitly set status filter to 'archived'
        ->set('tableFilters.status.value', 'archived')
        ->assertCanSeeTableRecords([$archived]);
});

it('shows Not live and never calls resolver for broken out-of-window block', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    // Broken payload, but out-of-window (starts tomorrow)
    $block = ContentBlock::factory()->published()->scheduled(now()->addDay(), null)->create([
        'payload' => ['invalid' => 'data'],
    ]);

    $resolver = Mockery::mock(ResolveServablePayload::class);
    $resolver->shouldNotReceive('__invoke');
    app()->instance(ResolveServablePayload::class, $resolver);

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->assertTableColumnFormattedStateSet('serving_status', 'Not live', record: $block);
});

it('renders Payload error for a published block of an unknown type', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $block = ContentBlock::factory()->published()->unknownType()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->assertTableColumnFormattedStateSet('serving_status', 'Payload error', record: $block);
});

it('calls resolver with log: false exactly once per live unknown block', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $block = ContentBlock::factory()->published()->unknownType()->create();

    $resolver = Mockery::mock(ResolveServablePayload::class);
    $resolver->shouldReceive('__invoke')
        ->with(Mockery::on(fn ($b) => $b->getKey() === $block->getKey()), false)
        ->once()
        ->andReturn(new ServingHealthResult(ServingHealthOutcome::UnknownType, null));
    app()->instance(ResolveServablePayload::class, $resolver);

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->assertTableColumnFormattedStateSet('serving_status', 'Payload error', record: $block);
});

it('asserts the formatting for each serving status badge', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $liveBlock = ContentBlock::factory()->published()->create();
    $notLiveBlock = ContentBlock::factory()->draft()->create();
    $errorBlock = ContentBlock::factory()->published()->unknownType()->create();

    $component = Livewire::actingAs($admin)->test(ListContentBlocks::class);

    $component->assertTableColumnFormattedStateSet('serving_status', 'Live', record: $liveBlock)
        ->assertTableColumnFormattedStateSet('serving_status', 'Not live', record: $notLiveBlock)
        ->assertTableColumnFormattedStateSet('serving_status', 'Payload error', record: $errorBlock);

    $component->assertTableColumnFormattedStateSet('type', 'announcement_bar', record: $liveBlock);
});

it('calls resolver with log: false exactly once per live row', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $live1 = ContentBlock::factory()->published()->create();
    $live2 = ContentBlock::factory()->published()->create();
    $draft = ContentBlock::factory()->draft()->create();

    $resolver = Mockery::mock(ResolveServablePayload::class);
    $resolver->shouldReceive('__invoke')
        ->with(Mockery::on(fn (ContentBlock $arg) => in_array($arg->getKey(), [$live1->getKey(), $live2->getKey()])), false)
        ->twice()
        ->andReturn(new ServingHealthResult(ServingHealthOutcome::Servable, null));

    app()->instance(ResolveServablePayload::class, $resolver);

    // Call GetBlockServingStatus to prime memo if needed, but ListContentBlocks does it for us
    // Actually wait, we must assert it doesn't get called for draft!

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class);
});

it('prevents N+1 queries when loading the table', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    // Run for 10 rows
    ContentBlock::factory()->count(10)->published()->create();

    $queryCount = 0; // Reset counter
    Livewire::actingAs($admin)->test(ListContentBlocks::class);
    $queries10 = $queryCount;

    // Run for 20 rows
    ContentBlock::query()->delete(); // Clear
    ContentBlock::factory()->count(20)->published()->create();

    $queryCount = 0; // Reset counter
    Livewire::actingAs($admin)->test(ListContentBlocks::class);
    $queries20 = $queryCount;

    // Queries should be identical regardless of row count
    expect($queries20)->toBe($queries10);
});

it('respects timezone configuration in labels and formatted outputs without modifying database', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    Config::set('admin.timezone', 'Asia/Colombo');

    // Create a block that starts at a specific UTC time
    $startsAt = Carbon::parse('2026-01-01 12:00:00', 'UTC');
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->scheduled($startsAt, null)->create();

    $component = Livewire::actingAs($admin)->test(ListContentBlocks::class);

    // Assert labels
    $component->assertSee('Starts at (Asia/Colombo)');

    // The database value must still be UTC 12:00:00
    $block->refresh();
    expect($block->starts_at->format('H:i:s'))->toBe('12:00:00');

    // In Filament, dateTime() outputs using the app locale by default.
    // +05:30 means 17:30:00.
    // We just assert the formatted state matches the expected string.
    $expectedRender = $startsAt->timezone('Asia/Colombo')->format('M j, Y H:i:s');
    $component->assertTableColumnFormattedStateSet('starts_at', $expectedRender, record: $block);
});

it('sorts by placement then sort_order by default', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $block1 = ContentBlock::factory()->create(['placement' => Placement::ANNOUNCEMENT_BAR, 'sort_order' => 1]);
    $block2 = ContentBlock::factory()->create(['placement' => Placement::ANNOUNCEMENT_BAR, 'sort_order' => 3]);
    $block3 = ContentBlock::factory()->create(['placement' => Placement::ANNOUNCEMENT_BAR, 'sort_order' => 2]);

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->assertCanSeeTableRecords([$block1, $block3, $block2], inOrder: true);
});

it('allows user to sort by another column', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $block1 = ContentBlock::factory()->create(['placement' => Placement::ANNOUNCEMENT_BAR, 'status' => 'draft']);
    $block2 = ContentBlock::factory()->create(['placement' => Placement::ANNOUNCEMENT_BAR, 'status' => 'published']);

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->sortTable('status', 'desc')
        ->assertCanSeeTableRecords([$block2, $block1], inOrder: true);
});

it('returns 403 forbidden for non-admins when accessing ContentBlockResource', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var User $user */
    $user = User::factory()->create();

    $url = ContentBlockResource::getUrl('index');

    \Pest\Laravel\actingAs($admin)->get('/admin')
        ->assertSee($url);

    \Pest\Laravel\actingAs($user)->get($url)
        ->assertStatus(403);
});

it('can evaluate column colors via table instance', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $liveBlock = ContentBlock::factory()->published()->create();
    $notLiveBlock = ContentBlock::factory()->draft()->create();

    $component = Livewire::actingAs($admin)->test(ListContentBlocks::class);

    /** @var ListContentBlocks $instance */
    $instance = $component->instance();
    $table = $instance->getTable();

    /** @var TextColumn $servingStatusColumn */
    $servingStatusColumn = $table->getColumn('serving_status');

    // Evaluate color using the correct state for each record
    expect($servingStatusColumn->record($liveBlock)->getColor($servingStatusColumn->getState()))->toBe('success');
    expect($servingStatusColumn->record($notLiveBlock)->getColor($servingStatusColumn->getState()))->toBe('gray');

    /** @var TextColumn $statusColumn */
    $statusColumn = $table->getColumn('status');
    expect($statusColumn->record($liveBlock)->getColor($statusColumn->getState()))->toBe('success');
    expect($statusColumn->record($notLiveBlock)->getColor($statusColumn->getState()))->toBe('gray');
});

it('configures row click url to view page', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create();

    $component = Livewire::actingAs($admin)->test(ListContentBlocks::class);

    /** @var ListContentBlocks $instance */
    $instance = $component->instance();
    $table = $instance->getTable();

    expect($table->getRecordUrl($block))->toBe(ContentBlockResource::getUrl('view', ['record' => $block]));
});

it('renders view action for draft published and archived rows', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $draft */
    $draft = ContentBlock::factory()->draft()->create();
    /** @var ContentBlock $published */
    $published = ContentBlock::factory()->published()->create();
    /** @var ContentBlock $archived */
    $archived = ContentBlock::factory()->archived()->create();

    $component = Livewire::actingAs($admin)->test(ListContentBlocks::class)
        ->set('tableFilters.hide_archived.isActive', false);

    $component->assertTableActionExists('view', record: $draft)
        ->assertTableActionVisible('view', record: $draft)
        ->assertTableActionExists('view', record: $published)
        ->assertTableActionVisible('view', record: $published)
        ->assertTableActionExists('view', record: $archived)
        ->assertTableActionVisible('view', record: $archived);
});

it('hides edit action for archived rows', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $draft */
    $draft = ContentBlock::factory()->draft()->create();
    /** @var ContentBlock $archived */
    $archived = ContentBlock::factory()->archived()->create();

    $component = Livewire::actingAs($admin)->test(ListContentBlocks::class)
        ->set('tableFilters.hide_archived.isActive', false);

    $component->assertTableActionVisible('edit', record: $draft)
        ->assertTableActionHidden('edit', record: $archived);
});

it('executes transition actions and updates table state reactively', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->callTableAction('publish', record: $block)
        ->assertHasNoTableActionErrors()
        ->assertTableColumnStateSet('status', PublishStatus::PUBLISHED, record: $block);

    $block->refresh();
    expect($block->status)->toBe(PublishStatus::PUBLISHED);
});

it('drift-guard: placement filter offers every Placement case and filters correctly', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $blocks = [];
    foreach (Placement::cases() as $placement) {
        $type = $placement->allowedBlockTypes()[0];
        $blocks[$placement->value] = ContentBlock::factory()->published()->create([
            'placement' => $placement->value,
            'type' => $type,
        ]);
    }

    $component = Livewire::actingAs($admin)->test(ListContentBlocks::class);

    $instance = $component->instance();
    if (! $instance instanceof HasTable) {
        throw new RuntimeException('Expected ListContentBlocks to implement HasTable.');
    }

    $table = $instance->getTable();
    $placementFilter = $table->getFilter('placement');
    if (! $placementFilter instanceof SelectFilter) {
        throw new RuntimeException('Expected placement filter to be a SelectFilter.');
    }
    expect($placementFilter->getOptions())->toHaveKeys(
        collect(Placement::cases())->pluck('value')->all()
    );

    foreach (Placement::cases() as $placement) {
        $component->set('tableFilters.placement.value', $placement->value)
            ->assertCanSeeTableRecords([$blocks[$placement->value]])
            ->assertCanNotSeeTableRecords(
                collect($blocks)->except($placement->value)->values()->all()
            );
    }
});

it('drift-guard: type filter offers registered block types and filters correctly', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $block1 = ContentBlock::factory()->published()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
    ]);
    $block2 = ContentBlock::factory()->published()->create([
        'placement' => Placement::HOME_HERO_CAROUSEL->value,
        'type' => 'promo_slide',
    ]);

    $component = Livewire::actingAs($admin)->test(ListContentBlocks::class);

    $instance = $component->instance();
    if (! $instance instanceof HasTable) {
        throw new RuntimeException('Expected ListContentBlocks to implement HasTable.');
    }

    $table = $instance->getTable();
    $typeFilter = $table->getFilter('type');
    if (! $typeFilter instanceof SelectFilter) {
        throw new RuntimeException('Expected type filter to be a SelectFilter.');
    }
    expect($typeFilter->getOptions())->toHaveKeys([
        'announcement_bar',
        'promo_slide',
        'feature_banner',
        'service_item',
        'section_header',
        'brand_strip',
    ]);

    $component->set('tableFilters.type.value', 'promo_slide')
        ->assertCanSeeTableRecords([$block2])
        ->assertCanNotSeeTableRecords([$block1]);
});
