<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\ContentBlock;

use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\CreateContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\EditContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ListContentBlocks;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ViewContentBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use ReflectionClass;
use Tests\Support\TestBlocks;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    TestBlocks::register();
});

it('characterizes tableSearch, tableSort, and tableFilters properties as having history false', function (): void {
    $ref = new ReflectionClass(ListContentBlocks::class);

    foreach (['tableSearch', 'tableSort', 'tableFilters'] as $property) {
        $prop = $ref->getProperty($property);
        $attributes = $prop->getAttributes(Url::class);
        expect($attributes)->not->toBeEmpty();
        /** @var Url $instance */
        $instance = $attributes[0]->newInstance();
        expect($instance->history)->toBeFalse();
    }
});

it('configures table pagination on ListContentBlocks with history false', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    // Create 15 blocks (exceeds default table per-page size of 10)
    ContentBlock::factory()->count(15)->published()->create();

    $component = Livewire::test(ListContentBlocks::class);

    /** @var ListContentBlocks $instance */
    $instance = $component->instance();

    $queryStrings = $instance->queryStringHandlesPagination();

    expect($queryStrings)->not->toBeEmpty()
        ->and($queryStrings)->toHaveKey('paginators.page')
        ->and($queryStrings['paginators.page']['history'])->toBeFalse();
});

it('redirects Create form save to edit page using assertRedirect', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $component = Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => 'Spring Sale Now On',
            'payload.link_url' => '/shop',
            'payload.tone' => 'info',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var ContentBlock $block */
    $block = ContentBlock::query()->latest('id')->firstOrFail();

    $component->assertRedirect(ContentBlockResource::getUrl('edit', ['record' => $block]));
});

it('retains Edit form save on current page with assertNoRedirect', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create([
        'payload' => [
            'text' => 'Initial text',
            'link_url' => '/initial',
            'tone' => 'info',
        ],
    ]);

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->fillForm([
            'payload.text' => 'Updated text',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNoRedirect();
});

it('executes Publish table row action without redirect using assertNoRedirect', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    Livewire::test(ListContentBlocks::class)
        ->callTableAction('publish', record: $block)
        ->assertHasNoTableActionErrors()
        ->assertNoRedirect();
});

it('executes Archive table row action without redirect using assertNoRedirect', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create();

    Livewire::test(ListContentBlocks::class)
        ->callTableAction('archive', record: $block)
        ->assertHasNoTableActionErrors()
        ->assertNoRedirect();
});

it('executes Enable table row action without redirect using assertNoRedirect', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->disabled()->create();

    Livewire::test(ListContentBlocks::class)
        ->callTableAction('enable', record: $block)
        ->assertHasNoTableActionErrors()
        ->assertNoRedirect();
});

it('executes Disable table row action without redirect using assertNoRedirect', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->enabled()->create();

    Livewire::test(ListContentBlocks::class)
        ->callTableAction('disable', record: $block)
        ->assertHasNoTableActionErrors()
        ->assertNoRedirect();
});

it('executes Publish header action on View page without redirect using assertNoRedirect', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->draft()->create();

    Livewire::test(ViewContentBlock::class, ['record' => $block->public_id])
        ->callAction('publish')
        ->assertHasNoActionErrors()
        ->assertNoRedirect();
});

it('executes Archive header action on View page without redirect using assertNoRedirect', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->create();

    Livewire::test(ViewContentBlock::class, ['record' => $block->public_id])
        ->callAction('archive')
        ->assertHasNoActionErrors()
        ->assertNoRedirect();
});

it('executes Enable header action on View page without redirect using assertNoRedirect', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->disabled()->create();

    Livewire::test(ViewContentBlock::class, ['record' => $block->public_id])
        ->callAction('enable')
        ->assertHasNoActionErrors()
        ->assertNoRedirect();
});

it('executes Disable header action on View page without redirect using assertNoRedirect', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->enabled()->create();

    Livewire::test(ViewContentBlock::class, ['record' => $block->public_id])
        ->callAction('disable')
        ->assertHasNoActionErrors()
        ->assertNoRedirect();
});

it('redirects to index on View or Edit mount failure', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'unknown_type',
        'payload' => ['text' => 'Unknown payload'],
    ]);

    Livewire::test(ViewContentBlock::class, ['record' => $block->public_id])
        ->assertRedirect(ContentBlockResource::getUrl('index'));

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->assertRedirect(ContentBlockResource::getUrl('index'));
});
