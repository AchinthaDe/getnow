<?php

declare(strict_types=1);

use App\Domain\Content\Actions\DisableContentBlock;
use App\Domain\Content\Actions\EnableContentBlock;
use App\Domain\Content\Actions\GetLayoutContent;
use App\Domain\Content\Actions\PublishContentBlock;
use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\CreateContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\EditContentBlock;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

dataset('homepage_placements', [
    'announcement_bar' => [
        Placement::ANNOUNCEMENT_BAR,
        'announcement_bar',
        2,
        [
            'text' => 'Good tech. Better everyday.',
            'secondary_text' => 'Free delivery on orders over Rs. 25,000',
            'locale_text' => 'Sri Lanka / LKR',
            'link_url' => null,
            'tone' => 'info',
        ],
    ],
    'home_hero_carousel' => [
        Placement::HOME_HERO_CAROUSEL,
        'promo_slide',
        1,
        [
            'theme' => 'sky',
            'eyebrow' => 'FIND YOUR FREQUENCY',
            'title_line_1' => 'Less noise.',
            'title_line_2' => 'More you.',
            'description' => 'Make room for better sound. Explore offers on everyday audio.',
            'cta_label' => 'Shop audio offers',
            'cta_url' => '/shop?category=audio',
            'image_url' => '/images/products/soundcore-space-2.jpg',
            'image_alt' => 'Soundcore Space 2 over-ear headphones',
            'label' => 'Audio offers',
        ],
    ],
    'home_hero_side' => [
        Placement::HOME_HERO_SIDE,
        'feature_banner',
        1,
        [
            'eyebrow' => 'MAKE ROOM FOR GREAT SOUND',
            'title_line_1' => 'Big sound.',
            'title_line_2' => 'Small footprint.',
            'description' => null,
            'cta_label' => 'Explore speakers',
            'cta_url' => '/shop?category=speakers',
            'image_url' => '/images/products/marshall-acton-iii.png',
            'image_alt' => 'Marshall Acton III speaker',
            'badge_text' => 'MARSHALL ACTON III',
        ],
    ],
    'home_service_strip' => [
        Placement::HOME_SERVICE_STRIP,
        'service_item',
        1,
        [
            'icon' => 'truck',
            'title' => 'Islandwide delivery',
            'subtitle' => 'From our store to your door',
            'link_url' => null,
        ],
    ],
    'home_categories_header' => [
        Placement::HOME_CATEGORIES_HEADER,
        'section_header',
        1,
        [
            'eyebrow' => 'FIND YOUR THING',
            'title' => 'A little something for every day.',
            'cta_label' => 'Explore all categories',
            'cta_url' => '/shop',
        ],
    ],
    'home_featured_header' => [
        Placement::HOME_FEATURED_HEADER,
        'section_header',
        1,
        [
            'eyebrow' => 'THE ONES TO KNOW',
            'title' => 'Your next favourites.',
            'cta_label' => 'Shop the collection',
            'cta_url' => '/shop?sort=featured',
        ],
    ],
    'home_collection_banner' => [
        Placement::HOME_COLLECTION_BANNER,
        'feature_banner',
        1,
        [
            'eyebrow' => 'POWER YOUR EVERYDAY',
            'title_line_1' => 'Low battery?',
            'title_line_2' => 'Not your energy.',
            'description' => 'Power banks, chargers, and clever little essentials.',
            'cta_label' => 'Stay charged',
            'cta_url' => '/shop?category=charging',
            'image_url' => '/images/products/shargeek-300.jpg',
            'image_alt' => 'Shargeek 300 power bank',
            'badge_text' => '24/7',
        ],
    ],
    'home_new_arrivals_header' => [
        Placement::HOME_NEW_ARRIVALS_HEADER,
        'section_header',
        1,
        [
            'eyebrow' => 'FRESH ON THE SHELF',
            'title' => 'New things. New possibilities.',
            'cta_label' => "See what's new",
            'cta_url' => '/shop?sort=newest',
        ],
    ],
    'home_brand_strip' => [
        Placement::HOME_BRAND_STRIP,
        'brand_strip',
        1,
        [
            'eyebrow' => 'GOOD COMPANY',
            'title' => 'Great brands.',
            'brands' => [
                'item-1' => ['label' => 'Apple', 'url' => '/shop?brand=Apple'],
                'item-2' => ['label' => 'Samsung', 'url' => '/shop?brand=Samsung'],
            ],
        ],
    ],
]);

it('creates and serves each allowed block type for every homepage placement in Filament while excluding non-live blocks', function (
    Placement $placement,
    string $type,
    int $expectedSchemaVersion,
    array $payload
) {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $component = Livewire::test(CreateContentBlock::class)
        ->set('data.placement', $placement->value);

    // Verify type options match allowedBlockTypes
    /** @var CreateContentBlock $instance */
    $instance = $component->instance();
    $form = $instance->getSchema('form');
    expect($form)->not->toBeNull();
    $typeField = $form->getComponent('type');
    expect($typeField)->toBeInstanceOf(Select::class);
    /** @var Select $typeField */
    $typeOptions = $typeField->getOptions();
    expect(array_keys($typeOptions))->toBe($placement->allowedBlockTypes());

    // Fill form and create
    $component->fillForm([
        'placement' => $placement->value,
        'type' => $type,
        'sort_order' => 0,
        'payload' => $payload,
    ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var ContentBlock $block */
    $block = ContentBlock::query()
        ->where('placement', $placement->value)
        ->where('type', $type)
        ->latest('id')
        ->firstOrFail();

    expect($block->schema_version)->toBe($expectedSchemaVersion)
        ->and($block->status)->toBe(PublishStatus::DRAFT)
        ->and($block->sort_order)->toBe(0);

    // 1. GetLayoutContent: draft block must NOT be served
    $layout = app(GetLayoutContent::class)();
    expect($layout->data->placements[$placement->value])->toBeEmpty();

    // 2. Publish block -> now served
    app(PublishContentBlock::class)($block->id);
    $layout = app(GetLayoutContent::class)();
    expect($layout->data->placements[$placement->value])->toHaveCount(1)
        ->and($layout->data->placements[$placement->value][0]->id)->toBe($block->public_id);

    // 3. Disable block -> omitted from layout
    app(DisableContentBlock::class)($block->id);
    $layout = app(GetLayoutContent::class)();
    expect($layout->data->placements[$placement->value])->toBeEmpty();

    // 4. Re-enable block -> served again
    app(EnableContentBlock::class)($block->id);
    $layout = app(GetLayoutContent::class)();
    expect($layout->data->placements[$placement->value])->toHaveCount(1);

    // 5. Future schedule -> omitted from layout
    app(UpdateContentBlock::class)(
        $block->id,
        new UpdateContentBlockData(starts_at: now()->addHour(), ends_at: null),
        $block->payload
    );
    $layout = app(GetLayoutContent::class)();
    expect($layout->data->placements[$placement->value])->toBeEmpty();

    // 6. Reset schedule -> served again
    app(UpdateContentBlock::class)(
        $block->id,
        new UpdateContentBlockData(starts_at: null, ends_at: null),
        $block->payload
    );
    $layout = app(GetLayoutContent::class)();
    expect($layout->data->placements[$placement->value])->toHaveCount(1);
})->with('homepage_placements');

it('edits block payloads and sort_order preserving repeater list structure', function () {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'placement' => Placement::HOME_BRAND_STRIP->value,
        'type' => 'brand_strip',
        'schema_version' => 1,
        'sort_order' => 0,
        'payload' => [
            'eyebrow' => 'GOOD COMPANY',
            'title' => 'Great brands.',
            'brands' => [
                ['label' => 'Apple', 'url' => '/shop?brand=Apple'],
            ],
        ],
    ]);

    app(PublishContentBlock::class)($block->id);

    Livewire::test(EditContentBlock::class, ['record' => $block->public_id])
        ->assertFormSet(['sort_order' => 0])
        ->fillForm([
            'sort_order' => 5,
            'payload.brands' => [
                'item-uuid-1' => ['label' => 'Anker', 'url' => '/shop?brand=Anker'],
                'item-uuid-2' => ['label' => 'Sony', 'url' => '/shop?brand=Sony'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $block->refresh();
    expect($block->sort_order)->toBe(5);

    $raw = DB::table('content_blocks')->where('id', $block->id)->first();
    expect($raw)->not->toBeNull();
    /** @var array<string, mixed> $rawPayload */
    $rawPayload = json_decode((string) $raw->payload, true, 512, JSON_THROW_ON_ERROR);
    expect(array_is_list($rawPayload['brands']))->toBeTrue()
        ->and(count($rawPayload['brands']))->toBeGreaterThanOrEqual(2);
});
