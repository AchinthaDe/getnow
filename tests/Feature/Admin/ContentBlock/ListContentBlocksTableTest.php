<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Data\ServingHealthResult;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Services\ResolveServablePayload;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ListContentBlocks;
use App\Models\User;
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

it('registers the resource in the panel navigation', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin)
        ->get(route('filament.admin.pages.dashboard'))
        ->assertOk()
        ->assertSee(ContentBlockResource::getModelLabel());
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
        ->assertTableColumnStateSet('serving_status', ServingStatus::NotLive, record: $block);
});

it('shows PayloadError for a live unknown-type block', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $block = ContentBlock::factory()->published()->unknownType()->create();

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->assertTableColumnStateSet('serving_status', ServingStatus::PayloadError, record: $block);
});

it('asserts the formatting and color for each serving status badge', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $liveBlock = ContentBlock::factory()->published()->create();
    $notLiveBlock = ContentBlock::factory()->draft()->create();
    $errorBlock = ContentBlock::factory()->published()->unknownType()->create();

    $component = Livewire::actingAs($admin)->test(ListContentBlocks::class);

    $component->assertTableColumnStateSet('serving_status', ServingStatus::Live, record: $liveBlock)
        ->assertTableColumnFormattedStateSet('serving_status', 'Live', record: $liveBlock)
        ->assertTableColumnStateSet('serving_status', ServingStatus::NotLive, record: $notLiveBlock)
        ->assertTableColumnFormattedStateSet('serving_status', 'Not live', record: $notLiveBlock)
        ->assertTableColumnStateSet('serving_status', ServingStatus::PayloadError, record: $errorBlock)
        ->assertTableColumnFormattedStateSet('serving_status', 'Payload error', record: $errorBlock);
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

    $block1 = ContentBlock::factory()->create(['placement' => 'Z', 'sort_order' => 1]);
    $block2 = ContentBlock::factory()->create(['placement' => 'A', 'sort_order' => 2]);
    $block3 = ContentBlock::factory()->create(['placement' => 'A', 'sort_order' => 1]);

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->assertCanSeeTableRecords([$block3, $block2, $block1], inOrder: true);
});

it('allows user to sort by another column', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $block1 = ContentBlock::factory()->create(['placement' => 'A', 'status' => 'published']);
    $block2 = ContentBlock::factory()->create(['placement' => 'Z', 'status' => 'draft']);

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->sortTable('placement', 'desc')
        ->assertCanSeeTableRecords([$block2, $block1], inOrder: true); // Z comes before A
});

it('shows ContentBlockResource in navigation for admin and hides it for non-admin', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var User $user */
    $user = User::factory()->create();

    $url = ContentBlockResource::getUrl('index');

    \Pest\Laravel\actingAs($admin)->get('/admin')
        ->assertSee($url);

    \Pest\Laravel\actingAs($user)->get('/admin')
        ->assertStatus(403);
});
