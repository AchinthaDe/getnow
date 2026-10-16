<?php

declare(strict_types=1);

use App\Domain\Content\Actions\DisableContentBlock;
use App\Domain\Content\Actions\GetBlockServingStatus;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\EditContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ListContentBlocks;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ViewContentBlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestBlocks;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    TestBlocks::register();
});

it('prevents publishing a 2nd active block in singleton placement', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $firstBlock */
    $firstBlock = ContentBlock::factory()->published()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
        'starts_at' => null,
        'ends_at' => null,
    ]);

    /** @var ContentBlock $secondBlock */
    $secondBlock = ContentBlock::factory()->draft()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
    ]);

    actingAs($admin);

    Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $secondBlock->public_id])
        ->mountAction('publish')
        ->callMountedAction()
        ->assertNotified('Capacity Exceeded');

    $secondBlock->refresh();
    expect($secondBlock->status)->toBe(PublishStatus::DRAFT);
});

it('prevents enabling a 2nd active block in singleton placement', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $firstBlock */
    $firstBlock = ContentBlock::factory()->published()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
        'is_enabled' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    /** @var ContentBlock $secondBlock */
    $secondBlock = ContentBlock::factory()->published()->disabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
    ]);

    actingAs($admin);

    Livewire::actingAs($admin)
        ->test(ListContentBlocks::class)
        ->callTableAction('enable', record: $secondBlock)
        ->assertNotified('Capacity Exceeded');

    $secondBlock->refresh();
    expect($secondBlock->is_enabled)->toBeFalse();
});

it('prevents schedule edit that overlaps existing active singleton block', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $activeBlock */
    $activeBlock = ContentBlock::factory()->published()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $futureStart = now()->addDays(7);
    /** @var ContentBlock $futureBlock */
    $futureBlock = ContentBlock::factory()->published()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
        'starts_at' => $futureStart,
        'ends_at' => null,
    ]);

    actingAs($admin);

    Livewire::actingAs($admin)
        ->test(EditContentBlock::class, ['record' => $futureBlock->public_id])
        ->fillForm([
            'starts_at' => now()->format('Y-m-d H:i:s'),
        ])
        ->call('save')
        ->assertNotified('Capacity Exceeded');

    $futureBlock->refresh();
    expect($futureBlock->starts_at?->format('Y-m-d H:i:s'))->toBe($futureStart->format('Y-m-d H:i:s'));
});

it('allows activating 2nd block once 1st block is disabled or archived', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();

    /** @var ContentBlock $firstBlock */
    $firstBlock = ContentBlock::factory()->published()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
        'starts_at' => null,
        'ends_at' => null,
    ]);

    /** @var ContentBlock $secondBlock */
    $secondBlock = ContentBlock::factory()->draft()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
    ]);

    app(DisableContentBlock::class)($firstBlock->id, $admin);

    actingAs($admin);

    Livewire::actingAs($admin)
        ->test(ViewContentBlock::class, ['record' => $secondBlock->public_id])
        ->mountAction('publish')
        ->callMountedAction()
        ->assertNotified('Content Block published');

    $secondBlock->refresh();
    expect($secondBlock->status)->toBe(PublishStatus::PUBLISHED)
        ->and(app(GetBlockServingStatus::class)($secondBlock)->status)->toBe(ServingStatus::Live);
});
