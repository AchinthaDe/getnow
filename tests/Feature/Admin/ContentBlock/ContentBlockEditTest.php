<?php

declare(strict_types=1);

use App\Domain\Content\Actions\ArchiveContentBlock;
use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Exceptions\IllegalStateTransitionException;
use App\Domain\Content\Exceptions\UnknownBlockTypeException;
use App\Domain\Content\Exceptions\UnsupportedSchemaVersionException;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\EditContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ListContentBlocks;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FilamentSmokeTest;
use Tests\Support\TestBlocks;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    TestBlocks::register();
});

it('renders list and edit pages for platform admin via smoke test', function () {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    FilamentSmokeTest::assertListPageRenders(ListContentBlocks::class);
    FilamentSmokeTest::assertEditPageRenders(EditContentBlock::class, $block);
});

it('updates payload through action and logs activity with changed payload', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'payload' => [
            'text' => 'Initial text',
            'link_url' => '/initial',
            'tone' => 'info',
        ],
    ]);

    actingAs($admin);

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->fillForm([
            'payload.text' => 'Updated text',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $block->refresh();
    expect($block->payload['text'])->toBe('Updated text');

    /** @var Activity $latestActivity */
    $latestActivity = Activity::latest()->first();
    expect($latestActivity)->not->toBeNull()
        ->and($latestActivity->event)->toBe('updated')
        ->and($latestActivity->properties['changed'])->toBe(['payload']);
});

it('does not log activity on no-op save for block without schedule', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'starts_at' => null,
        'ends_at' => null,
    ]);

    actingAs($admin);

    $initialActivityCount = Activity::count();

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Activity::count())->toBe($initialActivityCount);
});

it('hydrates schedule wall time symmetrically and avoids activity logging on no-op save under non-UTC timezone', function () {
    Config::set('admin.timezone', 'Asia/Colombo');

    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $startsAtUtc = Carbon::parse('2026-10-15 03:30:00', 'UTC');
    $endsAtUtc = Carbon::parse('2026-10-15 05:30:00', 'UTC');

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'starts_at' => $startsAtUtc,
        'ends_at' => $endsAtUtc,
    ]);

    actingAs($admin);

    $initialActivityCount = Activity::count();

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->assertSet('data.starts_at', '2026-10-15 09:00:00')
        ->assertSet('data.ends_at', '2026-10-15 11:00:00')
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Activity::count())->toBe($initialActivityCount);

    $block->refresh();
    expect($block->starts_at?->format('Y-m-d H:i:s'))->toBe('2026-10-15 03:30:00')
        ->and($block->ends_at?->format('Y-m-d H:i:s'))->toBe('2026-10-15 05:30:00');
});

it('persists correct UTC schedule when editing ends_at under non-UTC timezone and logs activity', function () {
    Config::set('admin.timezone', 'Asia/Colombo');

    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $startsAtUtc = Carbon::parse('2026-10-15 03:30:00', 'UTC');
    $endsAtUtc = Carbon::parse('2026-10-15 05:30:00', 'UTC');

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'starts_at' => $startsAtUtc,
        'ends_at' => $endsAtUtc,
    ]);

    actingAs($admin);

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->fillForm([
            'ends_at' => '2026-10-15 12:00:00',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $block->refresh();
    expect($block->ends_at?->format('Y-m-d H:i:s'))->toBe('2026-10-15 06:30:00');

    /** @var Activity $latestActivity */
    $latestActivity = Activity::latest()->first();
    expect($latestActivity)->not->toBeNull()
        ->and($latestActivity->event)->toBe('updated')
        ->and($latestActivity->properties['changed'])->toBe(['ends_at']);
});

it('hydrates form with serialized string enum for tone', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'payload' => [
            'text' => 'Tone check',
            'link_url' => null,
            'tone' => 'info',
        ],
    ]);

    actingAs($admin);

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->assertFormSet([
            'payload.tone' => 'info',
            'payload.text' => 'Tone check',
        ]);
});

it('handles hydration failure and redirects to index with notification', function (string $factoryState) {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->{$factoryState}()->create();

    actingAs($admin);

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->assertRedirect(ContentBlockResource::getUrl('index'))
        ->assertNotified();
})->with([
    'unknownType',
    'unsupportedVersion',
    'invalidAfterUpgrade',
    'failedUpgrade',
]);

it('returns 403 forbidden if block is archived after mount during livewire hydration', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create(['status' => PublishStatus::DRAFT]);

    actingAs($admin);

    $component = Livewire::test(EditContentBlock::class, ['record' => $block->public_id]);

    app(ArchiveContentBlock::class)($block->id);

    $component->call('save')
        ->assertForbidden();
});

it('catches IllegalStateTransitionException in handleRecordUpdate and notifies user without modifying block', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    actingAs($admin);

    app()->bind(UpdateContentBlock::class, fn () => new class
    {
        public function __invoke(): never
        {
            throw new IllegalStateTransitionException('Blocked transition');
        }
    });

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->fillForm(['payload.text' => 'Attempted change'])
        ->call('save')
        ->assertRedirect(ContentBlockResource::getUrl('index'))
        ->assertNotified();

    $block->refresh();
    expect($block->payload['text'])->not->toBe('Attempted change');
});

it('catches schema mismatch exceptions in handleRecordUpdate and notifies user', function (string $exceptionClass) {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    actingAs($admin);

    app()->bind(UpdateContentBlock::class, fn () => new class($exceptionClass)
    {
        public function __construct(private string $exceptionClass) {}

        public function __invoke(): never
        {
            throw new $this->exceptionClass('Schema mismatch');
        }
    });

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->fillForm(['payload.text' => 'Attempted change'])
        ->call('save')
        ->assertRedirect(ContentBlockResource::getUrl('index'))
        ->assertNotified();
})->with([
    UnknownBlockTypeException::class,
    UnsupportedSchemaVersionException::class,
]);

it('denies non-admin and denies editing archived block on mount', function () {
    /** @var User $user */
    $user = User::factory()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    actingAs($user);
    get(ContentBlockResource::getUrl('edit', ['record' => $block]))
        ->assertForbidden();

    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $archivedBlock */
    $archivedBlock = ContentBlock::factory()->archived()->create();

    actingAs($admin);
    get(ContentBlockResource::getUrl('edit', ['record' => $archivedBlock]))
        ->assertForbidden();
});

it('hides edit action for archived content block in list table', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $archivedBlock */
    $archivedBlock = ContentBlock::factory()->archived()->create();
    /** @var ContentBlock $draftBlock */
    $draftBlock = ContentBlock::factory()->create();

    actingAs($admin);

    Livewire::test(ListContentBlocks::class)
        ->filterTable('status', 'archived')
        ->assertTableActionHidden('edit', $archivedBlock);

    Livewire::test(ListContentBlocks::class)
        ->filterTable('status', 'draft')
        ->assertTableActionVisible('edit', $draftBlock);
});

it('renders live change notice for published block and hides it for draft block', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $publishedBlock */
    $publishedBlock = ContentBlock::factory()->published()->create();
    /** @var ContentBlock $draftBlock */
    $draftBlock = ContentBlock::factory()->create(['status' => PublishStatus::DRAFT]);

    actingAs($admin);

    Livewire::test(EditContentBlock::class, ['record' => $publishedBlock->public_id])
        ->assertSee('Changes made to this block will go live immediately on the storefront.');

    Livewire::test(EditContentBlock::class, ['record' => $draftBlock->public_id])
        ->assertDontSee('Changes made to this block will go live immediately on the storefront.');
});

it('exposes no header actions on edit page and registers index and edit routes', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    actingAs($admin);

    $component = Livewire::test(EditContentBlock::class, ['record' => $block->public_id]);
    /** @var EditContentBlock $instance */
    $instance = $component->instance();
    expect($instance->getCachedHeaderActions())->toBeEmpty();

    expect(ContentBlockResource::getPages())->toHaveKeys(['index', 'edit']);
});

it('does not expose status, is_enabled, sort_order, schema_version or type fields on edit form', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    actingAs($admin);

    $component = Livewire::test(EditContentBlock::class, ['record' => $block->public_id]);

    $component->assertFormFieldDoesNotExist('status')
        ->assertFormFieldDoesNotExist('is_enabled')
        ->assertFormFieldDoesNotExist('sort_order')
        ->assertFormFieldDoesNotExist('schema_version')
        ->assertFormFieldDoesNotExist('type');
});

it('displays raw stored placement if placement enum is null', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    $block->forceFill(['placement' => 'legacy_custom_placement'])->save();

    actingAs($admin);

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->assertSee('legacy_custom_placement');
});

it('resolves edit route with public_id and returns 404 for numeric id', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create();

    actingAs($admin);

    expect(ContentBlockResource::getUrl('edit', ['record' => $block]))
        ->toContain($block->public_id);

    get('/admin/content-blocks/'.$block->id.'/edit')
        ->assertNotFound();
});
