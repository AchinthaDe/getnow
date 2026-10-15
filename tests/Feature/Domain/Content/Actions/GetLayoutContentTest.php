<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Content\Actions;

use App\Domain\Content\Actions\GetLayoutContent;
use App\Domain\Content\Actions\GetMenuTree;
use App\Domain\Content\Contracts\BlockDefinition;
use App\Domain\Content\Contracts\BlockRegistry;
use App\Domain\Content\Data\BlockPayload;
use App\Domain\Content\Enums\MenuKey;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\ServingHealthOutcome;
use App\Domain\Content\Enums\Tone;
use App\Domain\Content\Models\ContentBlock;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Domain\Content\Services\ContentBlockRegistry;
use App\Domain\Content\Settings\StorefrontSettings;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
    $v2Definition = new class implements BlockDefinition
    {
        public function typeKey(): string
        {
            return 'announcement_bar';
        }

        public function schemaVersion(): int
        {
            return 2;
        }

        public function dataClass(): string
        {
            return get_class(new class extends BlockPayload
            {
                public string $text = '';

                public ?string $link_url = null;

                public Tone $tone = Tone::INFO;

                public bool $upgraded = false;

                /**
                 * @param  Arrayable<string, mixed>|array<string, mixed>  $payload
                 */
                public static function validateAndCreate(Arrayable|array $payload): static
                {
                    $data = $payload instanceof Arrayable ? $payload->toArray() : $payload;
                    $instance = new self;
                    $instance->text = (string) ($data['text'] ?? '');
                    $instance->link_url = isset($data['link_url']) ? (string) $data['link_url'] : null;
                    $instance->tone = isset($data['tone']) && $data['tone'] instanceof Tone ? $data['tone'] : Tone::INFO;
                    $instance->upgraded = (bool) ($data['upgraded'] ?? false);

                    return $instance;
                }
            });
        }

        public function upgradePayload(int $fromVersion, array $payload): array
        {
            if ($fromVersion === 1) {
                $payload['upgraded'] = true;
            }

            return $payload;
        }
    };

    $customRegistry = new ContentBlockRegistry;
    $customRegistry->register($v2Definition);
    app()->instance(BlockRegistry::class, $customRegistry);

    ContentBlock::factory()->published()->enabled()->create([
        'placement' => Placement::ANNOUNCEMENT_BAR->value,
        'type' => 'announcement_bar',
        'schema_version' => 1,
        'payload' => [
            'text' => 'Upgrade Test',
            'tone' => 'info',
            'link_url' => null,
        ],
    ]);

    $response = app(GetLayoutContent::class)();

    expect($response->data->placements[Placement::ANNOUNCEMENT_BAR->value])->toHaveCount(1)
        ->and($response->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->version)->toBe(2)
        ->and($response->data->placements[Placement::ANNOUNCEMENT_BAR->value][0]->data['upgraded'])->toBeTrue();
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

    expect($queriesN)->toHaveCount(3)
        ->and($queries2N)->toHaveCount(3);
});
