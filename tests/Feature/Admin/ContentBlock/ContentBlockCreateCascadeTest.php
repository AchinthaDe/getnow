<?php

declare(strict_types=1);

use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PlacementPage;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\CreateContentBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestBlocks;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    TestBlocks::register();
});

it('disables placement select until page is chosen', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    Livewire::actingAs($admin)
        ->test(CreateContentBlock::class)
        ->assertFormFieldIsDisabled('placement');
});

it('cascades page to placement and auto-selects type for single-type placements', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $component = Livewire::actingAs($admin)
        ->test(CreateContentBlock::class)
        ->set('data.page', PlacementPage::HOME->value)
        ->assertFormFieldIsEnabled('placement')
        ->set('data.placement', Placement::HOME_HERO_SIDE->value);

    expect($component->get('data.type'))->toBe('feature_banner');
});

it('changing page clears placement, type, and payload', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $component = Livewire::actingAs($admin)
        ->test(CreateContentBlock::class)
        ->set('data.page', PlacementPage::HOME->value)
        ->set('data.placement', Placement::HOME_HERO_SIDE->value)
        ->set('data.payload', ['title' => 'Test title'])
        ->set('data.page', PlacementPage::GLOBAL_LAYOUT->value);

    expect($component->get('data.placement'))->toBeNull()
        ->and($component->get('data.type'))->toBeNull()
        ->and($component->get('data.payload'))->toBe([]);
});

it('creates block successfully when filled in order: page, placement, type, payload', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    Livewire::actingAs($admin)
        ->test(CreateContentBlock::class)
        ->fillForm([
            'page' => PlacementPage::GLOBAL_LAYOUT->value,
            'placement' => Placement::ANNOUNCEMENT_BAR->value,
            'type' => 'announcement_bar',
            'payload' => [
                'text' => 'Announcement text',
                'tone' => 'info',
                'link_url' => null,
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var ContentBlock $created */
    $created = ContentBlock::query()->latest('id')->firstOrFail();
    expect($created->placement)->toBe(Placement::ANNOUNCEMENT_BAR)
        ->and($created->type)->toBe('announcement_bar')
        ->and($created->status)->toBe(PublishStatus::DRAFT);
});
