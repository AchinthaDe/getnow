<?php

declare(strict_types=1);

use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PlacementPage;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\CreateContentBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestBlocks;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    TestBlocks::register();
});

it('cascades page to placement and auto-selects type for single-type placements', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $component = Livewire::actingAs($admin)
        ->test(CreateContentBlock::class)
        ->set('data.page', PlacementPage::HOME->value)
        ->assertFormFieldExists('placement')
        ->set('data.placement', Placement::HOME_HERO_SIDE->value);

    expect($component->get('data.type'))->toBe('feature_banner');
});

it('preserves backward compatibility when placement is filled without page', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    $component = Livewire::actingAs($admin)
        ->test(CreateContentBlock::class)
        ->fillForm([
            'placement' => Placement::ANNOUNCEMENT_BAR->value,
            'type' => 'announcement_bar',
            'payload' => [
                'text' => 'Direct fill without page',
                'tone' => 'info',
                'link_url' => null,
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    \Pest\Laravel\assertDatabaseHas('content_blocks', [
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
    ]);
});
