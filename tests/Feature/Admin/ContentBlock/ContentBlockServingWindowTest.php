<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\ContentBlock;

use App\Domain\Content\Enums\ServingStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\CreateContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ListContentBlocks;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ViewContentBlock;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\Support\TestBlocks;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    TestBlocks::register();
});

it('renders field label displaying resolved admin timezone under non-UTC configuration', function (): void {
    Config::set('admin.timezone', 'Asia/Colombo');

    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $component = Livewire::test(CreateContentBlock::class);

    $component->assertSee('Starts at (Asia/Colombo)')
        ->assertSee('Ends at (Asia/Colombo)');

    /** @var CreateContentBlock $instance */
    $instance = $component->instance();
    /** @var DateTimePicker $startsAtComponent */
    $startsAtComponent = $instance->form->getComponent('starts_at');
    /** @var DateTimePicker $endsAtComponent */
    $endsAtComponent = $instance->form->getComponent('ends_at');

    expect($startsAtComponent->getLabel())->toBe('Starts at (Asia/Colombo)')
        ->and($endsAtComponent->getLabel())->toBe('Ends at (Asia/Colombo)');
});

it('persists entered schedule converted to UTC under non-UTC admin timezone', function (): void {
    Config::set('admin.timezone', 'Asia/Colombo');

    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    Livewire::test(CreateContentBlock::class)
        ->fillForm([
            'placement' => 'announcement_bar',
            'type' => 'announcement_bar',
            'payload.text' => 'Timezone schedule test',
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

it('renders Live badge on List and View pages when window opens without database write', function (): void {
    Carbon::setTestNow('2026-10-15 10:00:00');

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->published()->scheduled(now()->addMinutes(5), null)->create();

    // Advance time past starts_at
    Carbon::setTestNow('2026-10-15 10:06:00');

    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    // List page renders Live badge
    Livewire::test(ListContentBlocks::class)
        ->assertTableColumnStateSet('serving_status', ServingStatus::Live, record: $block)
        ->assertSee('Live');

    // View page renders Live badge
    Livewire::test(ViewContentBlock::class, ['record' => $block->public_id])
        ->assertSee('Live');
});
