<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Content\Actions;

use App\Domain\Catalog\Actions\Category\CreateCategory;
use App\Domain\Catalog\Actions\Category\DeactivateCategory;
use App\Domain\Catalog\Actions\Category\GetCategoryTree;
use App\Domain\Catalog\Actions\Category\UpdateCategory;
use App\Domain\Catalog\Data\Category\CreateCategoryData;
use App\Domain\Catalog\Data\Category\UpdateCategoryData;
use App\Domain\Catalog\Enums\CategoryTreeAudience;
use App\Domain\Content\Actions\GetLayoutContent;
use App\Domain\Content\Actions\GetMenuTree;
use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Enums\MenuItemKind;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Domain\Content\Settings\StorefrontSettings;
use Closure;
use Database\Factories\CategoryFactory;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsQueryCounts;
use Tests\Support\TestBlocks;

beforeEach(function (): void {
    TestBlocks::register();
});

dataset('failing_servable_outcomes', function () {
    return [
        'UnknownType' => [
            fn () => ContentBlock::factory()->published()->enabled()->unknownType()->create([
                'placement' => Placement::ANNOUNCEMENT_BAR->value,
                'sort_order' => 0,
            ]),
            ServingHealthOutcome::UnknownType,
        ],
        'UnsupportedVersion' => [
            fn () => ContentBlock::factory()->published()->enabled()->unsupportedVersion()->create([
                'placement' => Placement::ANNOUNCEMENT_BAR->value,
                'sort_order' => 0,
            ]),
            ServingHealthOutcome::UnsupportedVersion,
        ],
        'InvalidAfterUpgrade' => [
            fn () => ContentBlock::factory()->published()->enabled()->invalidAfterUpgrade()->create([
                'placement' => Placement::ANNOUNCEMENT_BAR->value,
                'sort_order' => 0,
            ]),
            ServingHealthOutcome::InvalidAfterUpgrade,
        ],
        'UpgradeFailed' => [
            fn () => ContentBlock::factory()->published()->enabled()->failedUpgrade()->create([
                'placement' => Placement::ANNOUNCEMENT_BAR->value,
                'sort_order' => 0,
            ]),
            ServingHealthOutcome::UpgradeFailed,
        ],
    ];
});

it('returns storefront settings matching configured values', function (): void {
    $settings = app(StorefrontSettings::class);
    $settings->site_name = 'Custom Store';
    $settings->default_meta_description = 'Wholesale store';
    $settings->search_placeholder = 'Find models';
    $settings->footer_brand_text = 'Brand Text';
    $settings->save();

    $response = app(GetLayoutContent::class)();

    expect($response->data->settings->site_name)->toBe('Custom Store')
        ->and($response->data->settings->default_meta_description)->toBe('Wholesale store')
        ->and($response->data->settings->search_placeholder)->toBe('Find models')
        ->and($response->data->settings->footer_brand_text)->toBe('Brand Text');
});

it('includes every defined placement and menu key even when empty or unprovisioned', function (): void {
    ContentBlock::query()->delete();
    MenuItem::query()->delete();
    Menu::query()->delete();

    $response = app(GetLayoutContent::class)();

    expect($response->data->placements)->toHaveKey(Placement::ANNOUNCEMENT_BAR->value)
        ->and($response->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toBe([]);

    foreach (MenuKey::cases() as $menuKey) {
        expect($response->data->menus)->toHaveKey($menuKey->value)
            ->and($response->data->menus[$menuKey->value]->title)->toBeNull()
            ->and($response->data->menus[$menuKey->value]->items)->toBe([]);
    }
});

it('excludes draft, archived, disabled, future-scheduled, and expired blocks from placements', function (): void {
    Carbon::setTestNow('2026-10-15 10:00:00');

    // Create ONLY the 6 excluded blocks
    ContentBlock::factory()->draft()->enabled()->create(['placement' => Placement::ANNOUNCEMENT_BAR->value]);
    ContentBlock::factory()->archived()->enabled()->create(['placement' => Placement::ANNOUNCEMENT_BAR->value]);
    ContentBlock::factory()->published()->disabled()->create(['placement' => Placement::ANNOUNCEMENT_BAR->value]);
    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'starts_at' => now()->addHour(),
    ]);
    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'ends_at' => now()->subHour(),
    ]);
    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'ends_at' => now(),
    ]);

    // Exclusion phase: verify all 6 blocks are omitted
    $response = app(GetLayoutContent::class)();
    expect($response->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toBe([]);

    // Boundary active phase: verify exact starts_at == now() is served
    /** @var ContentBlock $boundaryActive */
    $boundaryActive = ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'starts_at' => now(),
        'ends_at' => null,
    ]);

    $responseWithActive = app(GetLayoutContent::class)();
    expect($responseWithActive->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toHaveCount(1)
        ->and($responseWithActive->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->id)->toBe($boundaryActive->public_id);
});

it('enforces placement maxCount and selects candidate by D8 tie break order', function (): void {
    Carbon::setTestNow('2026-10-15 10:00:00');

    // Sub-case A: sort_order ASC
    ContentBlock::query()->delete();
    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'sort_order' => 1,
        'starts_at' => null,
    ]);
    /** @var ContentBlock $winnerA */
    $winnerA = ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'sort_order' => 0,
        'starts_at' => null,
    ]);

    $respA = app(GetLayoutContent::class)();
    expect($respA->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toHaveCount(1)
        ->and($respA->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->id)->toBe($winnerA->public_id);

    // Sub-case B: starts_at DESC NULLS LAST
    ContentBlock::query()->delete();
    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'sort_order' => 0,
        'starts_at' => null, // NULLS LAST
    ]);
    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'sort_order' => 0,
        'starts_at' => now()->subMinutes(10),
    ]);
    /** @var ContentBlock $winnerB */
    $winnerB = ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'sort_order' => 0,
        'starts_at' => now()->subMinutes(5),
    ]);

    $respB = app(GetLayoutContent::class)();
    expect($respB->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toHaveCount(1)
        ->and($respB->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->id)->toBe($winnerB->public_id);

    // Sub-case C: id DESC
    ContentBlock::query()->delete();
    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'sort_order' => 0,
        'starts_at' => null,
    ]);
    /** @var ContentBlock $winnerC */
    $winnerC = ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'sort_order' => 0,
        'starts_at' => null,
    ]);

    $respC = app(GetLayoutContent::class)();
    expect($respC->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toHaveCount(1)
        ->and($respC->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->id)->toBe($winnerC->public_id);
});

it('falls back to next valid candidate when top candidate is unservable', function (Closure $failingBlockFactory, ServingHealthOutcome $outcome): void {
    /** @var ContentBlock $corruptBlock */
    $corruptBlock = $failingBlockFactory();

    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'sort_order' => 1,
        'payload' => [
            'text' => 'Fallback Block',
            'tone' => 'info',
            'link_url' => null,
        ],
    ]);

    $response = app(GetLayoutContent::class)();

    expect($response->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toHaveCount(1)
        ->and($response->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->data['text'])->toBe('Fallback Block');

    $cacheKey = "serving_failure:{$corruptBlock->public_id}:{$outcome->value}";
    expect(Cache::has($cacheKey))->toBeTrue();
})->with('failing_servable_outcomes');

it('returns empty placement array when all candidate blocks are corrupt', function (): void {
    ContentBlock::factory()->published()->enabled()->unknownType()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'sort_order' => 0,
    ]);

    $response = app(GetLayoutContent::class)();
    expect($response->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toBe([]);
});

it('skips blocks whose type is not permitted for the placement', function (): void {
    /** @var BlockRegistry $registry */
    $registry = app(BlockRegistry::class);
    $otherDefinition = new class implements BlockDefinition
    {
        public function typeKey(): string
        {
            return 'other_type';
        }

        public function schemaVersion(): int
        {
            return 1;
        }

        public function dataClass(): string
        {
            return get_class(new class extends BlockPayload
            {
                public string $value = 'other';

                /**
                 * @param  Arrayable<string, mixed>|array<string, mixed>  $payload
                 */
                public static function validateAndCreate(Arrayable|array $payload): static
                {
                    return new self;
                }
            });
        }

        public function upgradePayload(int $fromVersion, array $payload): array
        {
            return $payload;
        }
    };
    $registry->register($otherDefinition);

    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'other_type',
        'schema_version' => 1,
        'payload' => ['value' => 'other'],
    ]);

    $response = app(GetLayoutContent::class)();
    expect($response->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toBe([]);
});

it('upgrades older payload schemas to current version', function (): void {
    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
        'schema_version' => 1,
        'payload' => [
            'text' => 'Old v1 text',
            'tone' => 'info',
            'link_url' => null,
        ],
    ]);

    $response = app(GetLayoutContent::class)();

    expect($response->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toHaveCount(1)
        ->and($response->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->version)->toBe(2)
        ->and($response->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->data['text'])->toBe('Old v1 text')
        ->and($response->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->data['secondary_text'])->toBeNull()
        ->and($response->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->data['locale_text'])->toBeNull();
});

it('cascades exclusion to children of disabled parent items while retaining enabled siblings in strict sort order', function (): void {
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();

    $root1 = MenuItem::factory()->forMenu($menu)->create([
        'is_enabled' => false,
        'sort_order' => 0,
        'label' => 'Root 1',
    ]);
    $child1A = MenuItem::factory()->childOf($root1)->forMenu($menu)->create([
        'is_enabled' => true,
        'sort_order' => 0,
        'label' => 'Child 1A',
    ]);

    $root2 = MenuItem::factory()->forMenu($menu)->create([
        'is_enabled' => true,
        'sort_order' => 1,
        'label' => 'Root 2',
    ]);
    $child2B = MenuItem::factory()->childOf($root2)->forMenu($menu)->create([
        'is_enabled' => true,
        'sort_order' => 0,
        'label' => 'Child 2B',
    ]);
    $child2A = MenuItem::factory()->childOf($root2)->forMenu($menu)->create([
        'is_enabled' => true,
        'sort_order' => 0,
        'label' => 'Child 2A',
    ]);
    $child2C = MenuItem::factory()->childOf($root2)->forMenu($menu)->create([
        'is_enabled' => false,
        'sort_order' => 0,
        'label' => 'Child 2C',
    ]);

    $root3 = MenuItem::factory()->forMenu($menu)->create([
        'is_enabled' => true,
        'sort_order' => 0,
        'label' => 'Root 3',
    ]);

    // Admin tree preserves all disabled items
    $adminTree = app(GetMenuTree::class)(MenuKey::MAIN_NAV);
    expect($adminTree->items)->toHaveCount(3);

    // Public API excludes disabled root 1 and its child, excludes disabled child 2C, orders root 3 before root 2, and child 2B before child 2A
    $publicLayout = app(GetLayoutContent::class)();
    $items = $publicLayout->data->menus[MenuKey::MAIN_NAV->value]->items;

    expect($items)->toHaveCount(2)
        ->and($items[0]->id)->toBe($root3->public_id)
        ->and($items[1]->id)->toBe($root2->public_id);

    $root2Children = $items[1]->children;
    expect($root2Children)->toHaveCount(2)
        ->and($root2Children[0]->id)->toBe($child2B->public_id)
        ->and($root2Children[1]->id)->toBe($child2A->public_id);
});

it('maps menu item attributes to public contract shape without internal columns', function (): void {
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();

    $root = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Deals',
        'url' => '/deals',
        'icon' => null,
        'opens_in_new_tab' => true,
        'is_enabled' => true,
    ]);

    $child = MenuItem::factory()->childOf($root)->forMenu($menu)->create([
        'label' => 'Daily Deals',
        'url' => '/deals/daily',
        'icon' => null,
        'opens_in_new_tab' => false,
        'is_enabled' => true,
    ]);

    $response = app(GetLayoutContent::class)()->toArray();
    $rootData = $response['data']['menus'][MenuKey::MAIN_NAV->value]['items'][0];

    expect($rootData['id'])->toBe($root->public_id)
        ->and($rootData['label'])->toBe('Deals')
        ->and($rootData['url'])->toBe('/deals')
        ->and($rootData['icon'])->toBeNull()
        ->and($rootData['new_tab'])->toBeTrue()
        ->and($rootData)->not->toHaveKeys(['opens_in_new_tab', 'menu_id', 'parent_id', 'is_enabled', 'sort_order'])
        ->and($rootData['children'])->toHaveCount(1);

    $childData = $rootData['children'][0];
    expect($childData['id'])->toBe($child->public_id)
        ->and($childData['label'])->toBe('Daily Deals')
        ->and($childData['url'])->toBe('/deals/daily')
        ->and($childData['new_tab'])->toBeFalse()
        ->and($childData['children'])->toBe([])
        ->and($childData)->not->toHaveKeys(['opens_in_new_tab', 'menu_id', 'parent_id', 'is_enabled', 'sort_order']);
});

it('contains zero raw database primary keys or internal lifecycle attributes', function (): void {
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    $root = MenuItem::factory()->forMenu($menu)->create(['is_enabled' => true]);
    MenuItem::factory()->childOf($root)->forMenu($menu)->create(['is_enabled' => true]);

    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);

    $array = app(GetLayoutContent::class)()->toArray();

    $denyList = [
        'sort_order',
        'status',
        'is_enabled',
        'starts_at',
        'ends_at',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'menu_id',
        'parent_id',
        'opens_in_new_tab',
    ];

    $checkArray = function (array $items) use (&$checkArray, $denyList): void {
        foreach ($items as $key => $value) {
            expect(in_array($key, $denyList, true))->toBeFalse("Found denied key: {$key}");

            if ($key === 'id') {
                expect(is_string($value))->toBeTrue("Expected 'id' to be a string ULID")
                    ->and(is_numeric($value))->toBeFalse("Expected 'id' not to be numeric")
                    ->and(preg_match('/^[0-9a-hjkmnp-tv-z]{26}$/i', $value))->toBe(1, "Expected '{$value}' to be a valid 26-char Crockford ULID");
            }

            if (is_array($value)) {
                $checkArray($value);
            }
        }
    };

    $checkArray($array);
});

it('produces identical revision hash across timestamps and changes hash when content mutates', function (): void {
    Carbon::setTestNow('2026-10-15 10:00:00');

    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    $block = ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'payload' => [
            'text' => 'Initial Text',
            'tone' => 'info',
            'link_url' => null,
        ],
    ]);

    $res1 = app(GetLayoutContent::class)();
    $hash1 = $res1->meta->revision;
    $time1 = $res1->meta->generated_at;

    // Advance time: hash must remain deterministic, timestamp updates
    Carbon::setTestNow('2026-10-15 12:00:00');
    $res2 = app(GetLayoutContent::class)();
    $hash2 = $res2->meta->revision;
    $time2 = $res2->meta->generated_at;

    expect($hash1)->toBe($hash2)
        ->and($time1)->not->toBe($time2);

    // Mutate settings: hash changes
    $settings = app(StorefrontSettings::class);
    $settings->site_name = 'Mutated Store Name';
    $settings->save();

    $res3 = app(GetLayoutContent::class)();
    $hash3 = $res3->meta->revision;
    expect($hash3)->not->toBe($hash1);

    // Mutate block payload: hash changes
    $block->update(['payload' => ['text' => 'Mutated Text', 'tone' => 'info', 'link_url' => null]]);
    $res4 = app(GetLayoutContent::class)();
    $hash4 = $res4->meta->revision;
    expect($hash4)->not->toBe($hash3);

    // Mutate block is_enabled: hash changes
    $block->update(['is_enabled' => false]);
    $res5 = app(GetLayoutContent::class)();
    $hash5 = $res5->meta->revision;
    expect($hash5)->not->toBe($hash4);

    // Add enabled menu item: hash changes
    $menuItem = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'New Item',
        'is_enabled' => true,
    ]);
    $res6 = app(GetLayoutContent::class)();
    $hash6 = $res6->meta->revision;
    expect($hash6)->not->toBe($hash5);

    // Toggle menu item is_enabled: hash changes
    $menuItem->update(['is_enabled' => false]);
    $res7 = app(GetLayoutContent::class)();
    $hash7 = $res7->meta->revision;
    expect($hash7)->not->toBe($hash6);
});

it('executes layout retrieval within constant query bounds', function (): void {
    // Pre-warm settings
    $_ = app(StorefrontSettings::class)->site_name;

    ContentBlock::query()->delete();
    MenuItem::query()->delete();
    Menu::query()->delete();

    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();

    // Seed N=5 blocks and 10 menu items
    ContentBlock::factory()->count(5)->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);
    MenuItem::factory()->count(10)->forMenu($menu)->create(['is_enabled' => true]);

    DB::enableQueryLog();
    app(GetLayoutContent::class)();
    $queriesN = DB::getQueryLog();

    // Seed 2N=10 blocks and 20 menu items
    ContentBlock::factory()->count(5)->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
    ]);
    MenuItem::factory()->count(10)->forMenu($menu)->create(['is_enabled' => true]);

    DB::flushQueryLog();
    app(GetLayoutContent::class)();
    $queries2N = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queriesN)->toHaveCount(4)
        ->and($queries2N)->toHaveCount(4);
});

it('returns byte-identical JSON and matching revision hash for a link-only forest matching SHA-256 of canonical data', function (): void {
    ContentBlock::query()->delete();
    MenuItem::query()->delete();
    Menu::query()->delete();

    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create(['title' => 'Main Navigation']);
    $root1 = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Home',
        'url' => '/',
        'sort_order' => 0,
        'is_enabled' => true,
    ]);
    $root2 = MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Products',
        'url' => '/products',
        'sort_order' => 1,
        'is_enabled' => true,
    ]);
    $child2 = MenuItem::factory()->childOf($root2)->forMenu($menu)->create([
        'label' => 'Phones',
        'url' => '/products/phones',
        'sort_order' => 0,
        'is_enabled' => true,
    ]);

    // Disabled items that must be omitted
    MenuItem::factory()->forMenu($menu)->create([
        'label' => 'Disabled Root',
        'url' => '/disabled',
        'sort_order' => 2,
        'is_enabled' => false,
    ]);
    MenuItem::factory()->childOf($root2)->forMenu($menu)->create([
        'label' => 'Disabled Child',
        'url' => '/products/disabled',
        'sort_order' => 1,
        'is_enabled' => false,
    ]);

    $response = app(GetLayoutContent::class)();
    $canonicalData = $response->data->toArray();
    $expectedHash = hash(
        'sha256',
        json_encode($canonicalData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );

    expect($response->meta->revision)->toBe($expectedHash);
    $mainNavItems = $response->data->menus[MenuKey::MAIN_NAV->value]->items;
    expect($mainNavItems)->toHaveCount(2)
        ->and($mainNavItems[0]->id)->toBe($root1->public_id)
        ->and($mainNavItems[0]->label)->toBe('Home')
        ->and($mainNavItems[1]->id)->toBe($root2->public_id)
        ->and($mainNavItems[1]->children)->toHaveCount(1)
        ->and($mainNavItems[1]->children[0]->id)->toBe($child2->public_id);
});

it('issues zero category queries for a link-only menu forest using DB query log', function (): void {
    ContentBlock::query()->delete();
    MenuItem::query()->delete();
    Menu::query()->delete();

    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    MenuItem::factory()->forMenu($menu)->create([
        'kind' => MenuItemKind::LINK,
        'label' => 'Home',
        'url' => '/',
        'is_enabled' => true,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(GetLayoutContent::class)();
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries->filter(fn (array $q): bool => str_contains(strtolower($q['query']), 'categories')))->toBeEmpty();
});

it('issues matching category queries to GetCategoryTree Storefront whether layout contains one or ten category references', function (): void {
    $_ = app(StorefrontSettings::class)->site_name;

    ContentBlock::query()->delete();
    MenuItem::query()->delete();
    Menu::query()->delete();

    // Create 10 real categories before measuring $treeCount (m1)
    $categories = CategoryFactory::new()->count(10)->root()->create();

    // Setup baseline layout with at least one enabled root LINK item
    $mainMenu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    MenuItem::factory()->forMenu($mainMenu)->create([
        'kind' => MenuItemKind::LINK,
        'label' => 'Home',
        'url' => '/',
        'is_enabled' => true,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(GetLayoutContent::class)();
    $baseline = count(DB::getQueryLog());
    DB::disableQueryLog();

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(GetCategoryTree::class)(CategoryTreeAudience::Storefront);
    $treeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Case A: 1 category item
    MenuItem::factory()->category($categories[0]->public_id)->forMenu($mainMenu)->create([
        'is_enabled' => true,
        'sort_order' => 1,
    ]);

    $resA = AssertsQueryCounts::assertCount($baseline + $treeCount, function (): mixed {
        return app(GetLayoutContent::class)();
    });
    expect($resA->data->menus[MenuKey::MAIN_NAV->value]->items)->toHaveCount(2);

    // Case B: 10 category items across 3 menus
    $footer1 = Menu::factory()->forKey(MenuKey::FOOTER_COLUMN_1)->create();
    $footer2 = Menu::factory()->forKey(MenuKey::FOOTER_COLUMN_2)->create();

    for ($i = 1; $i < 4; $i++) {
        MenuItem::factory()->category($categories[$i]->public_id)->forMenu($mainMenu)->create([
            'is_enabled' => true,
            'sort_order' => $i + 1,
        ]);
    }
    for ($i = 4; $i < 7; $i++) {
        MenuItem::factory()->category($categories[$i]->public_id)->forMenu($footer1)->create([
            'is_enabled' => true,
            'sort_order' => $i,
        ]);
    }
    for ($i = 7; $i < 10; $i++) {
        MenuItem::factory()->category($categories[$i]->public_id)->forMenu($footer2)->create([
            'is_enabled' => true,
            'sort_order' => $i,
        ]);
    }

    $resB = AssertsQueryCounts::assertCount($baseline + $treeCount, function (): mixed {
        return app(GetLayoutContent::class)();
    });
    expect($resB->data->menus[MenuKey::MAIN_NAV->value]->items)->toHaveCount(5);
});

it('resolves category items in footer menus identically to main nav', function (): void {
    $cat = CategoryFactory::new()->root()->create([
        'name' => 'Support Center',
        'slug' => 'support',
    ]);
    $footer = Menu::factory()->forKey(MenuKey::FOOTER_COLUMN_1)->create();
    MenuItem::factory()->category($cat->public_id, null)->forMenu($footer)->create([
        'is_enabled' => true,
    ]);

    $response = app(GetLayoutContent::class)();
    $items = $response->data->menus[MenuKey::FOOTER_COLUMN_1->value]->items;

    expect($items)->toHaveCount(1)
        ->and($items[0]->label)->toBe('Support Center')
        ->and($items[0]->url)->toBe('/shop?category=support');
});

it('does not trigger lazy loading violations under strict models when resolving typed items', function (): void {
    $cat1 = CategoryFactory::new()->root()->create(['name' => 'Phones', 'slug' => 'phones']);
    $cat2 = CategoryFactory::new()->root()->create(['name' => 'Accessories', 'slug' => 'accessories']);

    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();

    $rootLink = MenuItem::factory()->forMenu($menu)->create([
        'kind' => MenuItemKind::LINK,
        'label' => 'Shop',
        'url' => '/shop',
        'is_enabled' => true,
    ]);
    MenuItem::factory()->childOf($rootLink)->forMenu($menu)->create([
        'kind' => MenuItemKind::LINK,
        'label' => 'All',
        'url' => '/shop/all',
        'is_enabled' => true,
    ]);

    $rootCategory = MenuItem::factory()->category($cat1->public_id)->forMenu($menu)->create([
        'is_enabled' => true,
        'label' => null,
    ]);
    MenuItem::factory()->childOf($rootCategory)->forMenu($menu)->create([
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => $cat2->public_id,
        'is_enabled' => true,
        'label' => null,
        'url' => null,
    ]);

    expect(fn () => app(GetLayoutContent::class)())->not->toThrow(LazyLoadingViolationException::class);
});

it('does not execute GetCategoryTree when an enabled typed item sits under a disabled parent', function (): void {
    $_ = app(StorefrontSettings::class)->site_name;

    ContentBlock::query()->delete();
    MenuItem::query()->delete();
    Menu::query()->delete();

    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    MenuItem::factory()->forMenu($menu)->create([
        'kind' => MenuItemKind::LINK,
        'label' => 'Home',
        'url' => '/',
        'is_enabled' => true,
    ]);

    // Measure $baseline query count before adding typed items (m3)
    DB::flushQueryLog();
    DB::enableQueryLog();
    app(GetLayoutContent::class)();
    $baseline = count(DB::getQueryLog());
    DB::disableQueryLog();

    $category = CategoryFactory::new()->root()->create();

    $disabledParent = MenuItem::factory()->forMenu($menu)->create([
        'kind' => MenuItemKind::LINK,
        'label' => 'Disabled Parent',
        'url' => '/disabled',
        'is_enabled' => false,
    ]);

    MenuItem::factory()->childOf($disabledParent)->forMenu($menu)->create([
        'kind' => MenuItemKind::CATEGORY,
        'category_public_id' => $category->public_id,
        'is_enabled' => true,
        'label' => null,
        'url' => null,
    ]);

    AssertsQueryCounts::assertCount($baseline, function (): void {
        $response = app(GetLayoutContent::class)();
        $items = $response->data->menus[MenuKey::MAIN_NAV->value]->items;
        expect($items)->toHaveCount(1)
            ->and($items[0]->label)->toBe('Home');
    });
});

it('emits nothing and issues zero queries against categories when a category_tree is disabled and is the only typed item', function (): void {
    $_ = app(StorefrontSettings::class)->site_name;

    ContentBlock::query()->delete();
    MenuItem::query()->delete();
    Menu::query()->delete();

    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    MenuItem::factory()->forMenu($menu)->create([
        'kind' => MenuItemKind::LINK,
        'label' => 'Home',
        'url' => '/',
        'is_enabled' => true,
    ]);

    // Measure $baseline query count before adding typed items (m3)
    DB::flushQueryLog();
    DB::enableQueryLog();
    app(GetLayoutContent::class)();
    $baseline = count(DB::getQueryLog());
    DB::disableQueryLog();

    $category = CategoryFactory::new()->root()->create();

    MenuItem::factory()->categoryTree($category->public_id)->forMenu($menu)->create([
        'is_enabled' => false,
    ]);

    AssertsQueryCounts::assertCount($baseline, function (): void {
        $response = app(GetLayoutContent::class)();
        $items = $response->data->menus[MenuKey::MAIN_NAV->value]->items;
        expect($items)->toHaveCount(1)
            ->and($items[0]->label)->toBe('Home');
    });
});

it('omits a disabled category item from layout output', function (): void {
    $cat = CategoryFactory::new()->root()->create();
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    MenuItem::factory()->category($cat->public_id)->forMenu($menu)->create([
        'is_enabled' => false,
    ]);

    $response = app(GetLayoutContent::class)();
    expect($response->data->menus[MenuKey::MAIN_NAV->value]->items)->toBeEmpty();
});

it('busts layout revision hash when category name changes on category item with null label while leaving url and menu updated_at unchanged', function (): void {
    $cat = CategoryFactory::new()->root()->create(['name' => 'Original Cat', 'slug' => 'original-cat']);
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    $menuItem = MenuItem::factory()->category($cat->public_id, null)->forMenu($menu)->create(['is_enabled' => true]);
    $originalUpdatedAt = $menuItem->updated_at?->toISOString();

    $res1 = app(GetLayoutContent::class)();
    $hash1 = $res1->meta->revision;
    $url1 = $res1->data->menus[MenuKey::MAIN_NAV->value]->items[0]->url;
    expect($res1->data->menus[MenuKey::MAIN_NAV->value]->items[0]->label)->toBe('Original Cat');

    // Mutate category name via UpdateCategory
    app(UpdateCategory::class)($cat, new UpdateCategoryData(name: 'Renamed Cat'));

    $res2 = app(GetLayoutContent::class)();
    $hash2 = $res2->meta->revision;
    $url2 = $res2->data->menus[MenuKey::MAIN_NAV->value]->items[0]->url;
    expect($res2->data->menus[MenuKey::MAIN_NAV->value]->items[0]->label)->toBe('Renamed Cat')
        ->and($hash2)->not->toBe($hash1)
        ->and($url2)->toBe($url1);

    $menuItem->refresh();
    expect($menuItem->url)->toBeNull()
        ->and($menuItem->updated_at?->toISOString())->toBe($originalUpdatedAt);
});

it('leaves layout revision hash unchanged when category name changes on category item with explicit label override', function (): void {
    $cat = CategoryFactory::new()->root()->create(['name' => 'Original Cat', 'slug' => 'original-cat']);
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    MenuItem::factory()->category($cat->public_id, 'Fixed Label')->forMenu($menu)->create(['is_enabled' => true]);

    $res1 = app(GetLayoutContent::class)();
    $hash1 = $res1->meta->revision;
    expect($res1->data->menus[MenuKey::MAIN_NAV->value]->items[0]->label)->toBe('Fixed Label');

    // Mutate category name via UpdateCategory
    app(UpdateCategory::class)($cat, new UpdateCategoryData(name: 'Renamed Cat'));

    $res2 = app(GetLayoutContent::class)();
    $hash2 = $res2->meta->revision;
    expect($res2->data->menus[MenuKey::MAIN_NAV->value]->items[0]->label)->toBe('Fixed Label')
        ->and($hash2)->toBe($hash1);
});

it('busts layout revision hash when referenced category is deactivated while preserving stored menu item row', function (): void {
    $cat = CategoryFactory::new()->root()->create(['name' => 'Deactivating Cat', 'slug' => 'deact-cat']);
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    $menuItem = MenuItem::factory()->category($cat->public_id)->forMenu($menu)->create(['is_enabled' => true]);

    $res1 = app(GetLayoutContent::class)();
    $hash1 = $res1->meta->revision;
    expect($res1->data->menus[MenuKey::MAIN_NAV->value]->items)->toHaveCount(1);

    // Deactivate category
    app(DeactivateCategory::class)($cat->public_id);

    $res2 = app(GetLayoutContent::class)();
    $hash2 = $res2->meta->revision;
    expect($res2->data->menus[MenuKey::MAIN_NAV->value]->items)->toBeEmpty()
        ->and($hash2)->not->toBe($hash1);

    expect(MenuItem::find($menuItem->id))->not->toBeNull();
});

it('busts layout revision hash when new visible root category is created with category_tree source', function (): void {
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    MenuItem::factory()->categoryTree(null)->forMenu($menu)->create(['is_enabled' => true]);

    $res1 = app(GetLayoutContent::class)();
    $hash1 = $res1->meta->revision;

    // Create new visible root category
    app(CreateCategory::class)(new CreateCategoryData(
        name: 'Brand New Category',
        parent_public_id: null,
        show_in_nav: true,
    ));

    $res2 = app(GetLayoutContent::class)();
    $hash2 = $res2->meta->revision;

    expect($hash2)->not->toBe($hash1)
        ->and(collect($res2->data->menus[MenuKey::MAIN_NAV->value]->items)->pluck('label'))->toContain('Brand New Category');
});

it('omits an active category child from layout content when its parent category is inactive', function (): void {
    $inactiveParent = CategoryFactory::new()->root()->inactive()->create();
    $activeChild = CategoryFactory::new()->child($inactiveParent)->create();

    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    MenuItem::factory()->category($activeChild->public_id)->forMenu($menu)->create(['is_enabled' => true]);

    $response = app(GetLayoutContent::class)();
    expect($response->data->menus[MenuKey::MAIN_NAV->value]->items)->toBeEmpty();
});

it('omits a category item from layout content when its target category has show_in_nav set to false', function (): void {
    $cat = CategoryFactory::new()->root()->create(['show_in_nav' => false]);
    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    MenuItem::factory()->category($cat->public_id)->forMenu($menu)->create(['is_enabled' => true]);

    $response = app(GetLayoutContent::class)();
    expect($response->data->menus[MenuKey::MAIN_NAV->value]->items)->toBeEmpty();
});

it('omits show_in_nav false roots and their subcategories from null-reference category_tree expansion in layout content', function (): void {
    $hiddenRoot = CategoryFactory::new()->root()->hiddenFromNav()->create(['name' => 'Hidden Root']);
    CategoryFactory::new()->child($hiddenRoot)->create(['name' => 'Child Of Hidden', 'show_in_nav' => true]);

    $menu = Menu::factory()->forKey(MenuKey::MAIN_NAV)->create();
    MenuItem::factory()->categoryTree(null)->forMenu($menu)->create(['is_enabled' => true]);

    $response = app(GetLayoutContent::class)();
    $labels = collect($response->data->menus[MenuKey::MAIN_NAV->value]->items)->pluck('label');
    expect($labels)->not->toContain('Hidden Root')
        ->and($labels)->not->toContain('Child Of Hidden');
});
