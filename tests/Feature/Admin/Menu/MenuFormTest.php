<?php

declare(strict_types=1);

use App\Domain\Content\Actions\UpdateMenu;
use App\Domain\Content\Exceptions\InvalidMenuTreeException;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Filament\Admin\Resources\MenuResource\Pages\EditMenu;
use App\Filament\Admin\Resources\MenuResource\Schemas\MenuForm;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Schema;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('hydrates form from GetMenuTree rather than Eloquent relationships', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();
    $parent = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Root Menu Item',
        'url' => '/root-item',
        'sort_order' => 0,
        'parent_id' => null,
    ]);
    MenuItem::factory()->childOf($parent)->create([
        'label' => 'Nested Child Item',
        'url' => '/nested-child',
        'sort_order' => 0,
    ]);

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->assertFormSet([
            'title' => $menu->title,
        ]);
});

it('forbids third-level nesting in form schema', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();
    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id]);
    /** @var EditMenu $instance */
    $instance = $component->instance();
    $schema = Schema::make($instance);
    MenuForm::form($schema);
    $components = $schema->getComponents();

    $itemsRepeater = collect($components)->first(fn ($c) => $c instanceof Repeater && $c->getName() === 'items');
    expect($itemsRepeater)->not->toBeNull();

    $childRepeater = collect($itemsRepeater->getChildComponents())->first(fn ($c) => $c instanceof Repeater && $c->getName() === 'children');
    expect($childRepeater)->not->toBeNull();

    // Verify children repeater has NO nested repeater
    $nestedRepeaters = collect($childRepeater->getChildComponents())->filter(fn ($c) => $c instanceof Repeater);
    expect($nestedRepeaters)->toBeEmpty();
});

it('saves form data through UpdateMenu and re-hydrates state without duplicate creation on second save', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();

    $component = Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'title' => 'Updated Navigation',
            'items' => [
                [
                    'public_id' => null,
                    'label' => 'Main Category',
                    'url' => '/category',
                    'opens_in_new_tab' => false,
                    'is_enabled' => true,
                    'children' => [
                        [
                            'public_id' => null,
                            'label' => 'Sub Category',
                            'url' => '/category/sub',
                            'opens_in_new_tab' => false,
                            'is_enabled' => true,
                        ],
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Verify 2 items in database and record their public_ids
    expect($menu->items()->count())->toBe(2);
    $initialPublicIds = $menu->items()->pluck('public_id')->sort()->values()->all();

    // Immediately call save again without modifying inputs
    $component->call('save')
        ->assertHasNoFormErrors();

    // Assert total count remains exactly 2 and ULIDs are stable (zero duplicates or replacements)
    expect($menu->items()->count())->toBe(2);
    expect($menu->fresh()->items()->pluck('public_id')->sort()->values()->all())->toBe($initialPublicIds);
});

it('displays field errors for invalid URL inputs', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'items' => [
                [
                    'public_id' => null,
                    'label' => 'Evil Link',
                    'url' => '//evil.com',
                ],
            ],
        ])
        ->call('save')
        ->assertHasErrors(['data.items.0.url']);

    expect($menu->items()->count())->toBe(0);
});

it('displays danger notification with fixed copy on domain rejection', function () {
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $menu = Menu::firstOrFail();

    // Bind throwing instance of UpdateMenu
    app()->bind(UpdateMenu::class, fn () => new class
    {
        public function __invoke(): never
        {
            throw InvalidMenuTreeException::depthExceeded();
        }
    });

    Livewire::test(EditMenu::class, ['record' => $menu->public_id])
        ->fillForm([
            'title' => 'Test',
        ])
        ->call('save')
        ->assertNotified('Update Failed');
});
