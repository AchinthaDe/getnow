<?php

declare(strict_types=1);

use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\Concerns\InteractsWithContentBlockActions;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\CreateContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ListContentBlocks;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FilamentSmokeTest;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(FilamentSmokeTest::class);

it('renders create page for platform admin via smoke test', function () {
    $this->assertCreatePageRenders(CreateContentBlock::class);
});

it('creates draft content block with causer and logs activity', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    Livewire::test(CreateContentBlock::class)
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

    expect($block->placement)->toBe(Placement::ANNOUNCEMENT_BAR)
        ->and($block->type)->toBe('announcement_bar')
        ->and($block->status)->toBe(PublishStatus::DRAFT)
        ->and($block->is_enabled)->toBeTrue()
        ->and($block->sort_order)->toBe(0)
        ->and($block->created_by)->toBe($admin->id)
        ->and($block->payload['text'])->toBe('Spring Sale Now On')
        ->and($block->payload['link_url'])->toBe('/shop')
        ->and($block->payload['tone'])->toBe('info');

    $activity = Activity::query()
        ->where('subject_type', $block->getMorphClass())
        ->where('subject_id', $block->getKey())
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id);
});

it('re-keys trait placement validation errors with data prefix', function () {
    $consumer = new class
    {
        use InteractsWithContentBlockActions {
            prepareCreateData as public;
        }
    };

    $thrown = null;
    try {
        $consumer->prepareCreateData(['placement' => 'invalid_placement']);
    } catch (ValidationException $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(ValidationException::class);
    /** @var ValidationException $thrown */
    expect($thrown->errors())->toHaveKey('data.placement');
});

it('[Action path] Text containing HTML tags is rejected with data.payload.text', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => '<b>Bold Text</b>',
            'payload.tone' => 'info',
        ])
        ->call('create')
        ->assertHasErrors(['data.payload.text']);
});

it('[Action path] Disallowed link scheme is rejected with data.payload.link_url', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => 'Valid announcement text',
            'payload.link_url' => 'javascript:alert(1)',
            'payload.tone' => 'info',
        ])
        ->call('create')
        ->assertHasErrors(['data.payload.link_url']);
});

it('[Action path] Schedule ends_at before starts_at is rejected with data.ends_at', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => 'Valid announcement text',
            'payload.tone' => 'info',
            'starts_at' => '2026-10-15 12:00:00',
            'ends_at' => '2026-10-15 10:00:00',
        ])
        ->call('create')
        ->assertHasErrors(['data.ends_at']);
});

it('rejects whitespace-only text with data.payload.text validation error', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => '    ',
            'payload.tone' => 'info',
        ])
        ->call('create')
        ->assertHasErrors(['data.payload.text']);
});

it('accepts safe relative and https link urls and converts empty link to null', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => 'Announcement with https URL',
            'payload.link_url' => 'https://example.com/promo',
            'payload.tone' => 'info',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var ContentBlock $block1 */
    $block1 = ContentBlock::query()->latest('id')->firstOrFail();
    expect($block1->payload['link_url'])->toBe('https://example.com/promo');

    Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => 'Announcement with empty URL',
            'payload.link_url' => '',
            'payload.tone' => 'info',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var ContentBlock $block2 */
    $block2 = ContentBlock::query()->latest('id')->firstOrFail();
    expect($block2->payload['link_url'])->toBeNull();
});

it('persists schedule in UTC when admin creates block in non-UTC timezone', function () {
    Config::set('admin.timezone', 'Asia/Colombo');

    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => 'Scheduled announcement',
            'payload.tone' => 'info',
            'starts_at' => '2026-10-15 09:00:00',
            'ends_at' => '2026-10-15 11:00:00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var ContentBlock $block */
    $block = ContentBlock::query()->latest('id')->firstOrFail();

    expect($block->starts_at?->format('Y-m-d H:i:s'))->toBe('2026-10-15 03:30:00')
        ->and($block->ends_at?->format('Y-m-d H:i:s'))->toBe('2026-10-15 05:30:00');
});

it('denies non-admin from creating content blocks', function () {
    /** @var User $user */
    $user = User::factory()->create();

    actingAs($user);

    get(ContentBlockResource::getUrl('create'))
        ->assertForbidden();

    Livewire::test(CreateContentBlock::class)
        ->assertForbidden();
});

it('registers exact resource routes and exposes create action on list page', function () {
    expect(array_keys(ContentBlockResource::getPages()))->toEqualCanonicalizing(['index', 'create', 'edit']);

    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    Livewire::test(ListContentBlocks::class)
        ->assertActionExists('create');
});

it('dynamically displays payload fields when placement and type are selected on create', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    Livewire::test(CreateContentBlock::class)
        ->assertFormFieldExists('placement')
        ->assertFormFieldExists('type')
        ->set('data.placement', 'announcement_bar')
        ->set('data.type', 'announcement_bar')
        ->assertFormFieldExists('payload.text')
        ->assertFormFieldExists('payload.link_url')
        ->assertFormFieldExists('payload.tone');
});

it('redirects to edit page after successful block creation', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    $component = Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => 'Redirect check',
            'payload.tone' => 'info',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var ContentBlock $block */
    $block = ContentBlock::query()->latest('id')->firstOrFail();

    $component->assertRedirect(ContentBlockResource::getUrl('edit', ['record' => $block]));
});

it('discards undeclared payload keys submitted during create', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    actingAs($admin);

    Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => 'Tamper test',
            'payload.tone' => 'info',
        ])
        ->set('data.payload.undeclared_key', 'malicious_content')
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var ContentBlock $block */
    $block = ContentBlock::query()->latest('id')->firstOrFail();

    expect($block->payload)->not->toHaveKey('undeclared_key');
});

