<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\ContentBlock;

use App\Domain\Content\Actions\UpdateContentBlock;
use App\Domain\Content\Data\UpdateContentBlockData;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\CreateContentBlock;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ListContentBlocks;
use App\Filament\Admin\Resources\ContentBlockResource\Pages\ViewContentBlock;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('Layer C Step 1: filters list table by each of the 9 placements', function (): void {
    (new ContentSeeder)->run();

    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $component = Livewire::test(ListContentBlocks::class);

    $instance = $component->instance();
    if (! $instance instanceof HasTable) {
        throw new \RuntimeException('Expected ListContentBlocks to implement HasTable.');
    }

    $filter = $instance->getTable()->getFilter('placement');
    if (! $filter instanceof SelectFilter) {
        throw new \RuntimeException('Expected placement filter to be a SelectFilter.');
    }

    $options = $filter->getOptions();

    foreach (Placement::cases() as $placement) {
        expect($options)->toHaveKey($placement->value);

        $matchingBlocks = ContentBlock::where('placement', $placement->value)
            ->where('status', '!=', PublishStatus::ARCHIVED->value)
            ->get();

        $nonMatchingBlocks = ContentBlock::where('placement', '!=', $placement->value)
            ->where('status', '!=', PublishStatus::ARCHIVED->value)
            ->get();

        $component->set('tableFilters.placement.value', $placement->value)
            ->assertCanSeeTableRecords($matchingBlocks)
            ->assertCanNotSeeTableRecords($nonMatchingBlocks);
    }
});

it('Layer C Step 2: creates one block of each of the 5 new types in Filament', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $blockSpecs = [
        'promo_slide' => [
            'placement' => Placement::HOME_HERO_CAROUSEL->value,
            'type' => 'promo_slide',
            'payload' => [
                'theme' => 'sky',
                'label' => 'New Flash Slide',
                'eyebrow' => 'LIMITED TIME',
                'title_line_1' => 'Weekend Clearance',
                'title_line_2' => 'Up to 50% Off',
                'description' => 'Unbeatable savings on audio gear.',
                'cta_label' => 'Grab Deal',
                'cta_url' => '/shop?deals=1',
                'image_url' => '/images/products/airpods-max.jpg',
                'image_alt' => 'AirPods Max banner',
            ],
        ],
        'feature_banner' => [
            'placement' => Placement::HOME_HERO_SIDE->value,
            'type' => 'feature_banner',
            'payload' => [
                'eyebrow' => 'HOT PICK',
                'title_line_1' => 'Sound Redefined',
                'title_line_2' => 'Marshall Major IV',
                'description' => 'Iconic design with 80+ hours of playtime.',
                'cta_label' => 'Discover',
                'cta_url' => '/shop?category=audio',
                'image_url' => '/images/products/marshall-major-4.jpg',
                'image_alt' => 'Marshall Major IV banner',
                'badge_text' => 'HOT',
            ],
        ],
        'service_item' => [
            'placement' => Placement::HOME_SERVICE_STRIP->value,
            'type' => 'service_item',
            'payload' => [
                'icon' => 'truck',
                'title' => 'Islandwide Courier',
                'subtitle' => 'Delivery within 48 hours',
                'link_url' => '/delivery',
            ],
        ],
        'section_header' => [
            'placement' => Placement::HOME_CATEGORIES_HEADER->value,
            'type' => 'section_header',
            'payload' => [
                'eyebrow' => 'TOP CATEGORIES',
                'title' => 'Browse by tech category.',
                'cta_label' => 'View all',
                'cta_url' => '/shop',
            ],
        ],
        'brand_strip' => [
            'placement' => Placement::HOME_BRAND_STRIP->value,
            'type' => 'brand_strip',
            'payload' => [
                'eyebrow' => 'OFFICIAL PARTNERS',
                'title' => 'Authorized retailers.',
                'brands' => [
                    ['label' => 'Google', 'url' => '/shop?brand=Google'],
                    ['label' => 'Nothing', 'url' => '/shop?brand=Nothing'],
                ],
            ],
        ],
    ];

    foreach ($blockSpecs as $type => $spec) {
        $data = [
            'placement' => $spec['placement'],
            'type' => $spec['type'],
            'sort_order' => 1,
            'payload' => $spec['payload'],
        ];

        Livewire::test(CreateContentBlock::class)
            ->fillForm($data)
            ->call('create')
            ->assertHasNoFormErrors();

        /** @var ContentBlock $created */
        $created = ContentBlock::where('type', $type)->latest('id')->firstOrFail();
        expect($created->status)->toBe(PublishStatus::DRAFT)
            ->and($created->placement->value)->toBe($spec['placement']);
    }
});

it('Layer C Step 3: opens View page for promo_slide and verifies read-only hydration', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $block = ContentBlock::factory()->create([
        'type' => 'promo_slide',
        'placement' => Placement::HOME_HERO_CAROUSEL->value,
        'schema_version' => 1,
        'payload' => [
            'theme' => 'sky',
            'label' => 'Hydration Test Slide',
            'eyebrow' => 'EXCLUSIVE',
            'title_line_1' => 'Sound Redefined',
            'title_line_2' => 'Sony WH-1000XM5',
            'description' => 'Industry-leading noise canceling.',
            'cta_label' => 'Listen Now',
            'cta_url' => '/shop?q=sony',
            'image_url' => '/images/products/sony-wh1000xm5.jpg',
            'image_alt' => 'Sony headphones',
        ],
    ]);
    if (! $block instanceof ContentBlock) {
        throw new \RuntimeException('Expected ContentBlock instance.');
    }

    $response = get('/admin/content-blocks/'.$block->public_id);
    $response->assertOk();

    $component = Livewire::test(ViewContentBlock::class, ['record' => $block->public_id]);
    $component->assertSuccessful();

    /** @var ViewContentBlock $instance */
    $instance = $component->instance();
    expect($instance->form->isDisabled())->toBeTrue()
        ->and($component->get('data.payload.title_line_1'))->toBe('Sound Redefined')
        ->and($component->get('data.payload.label'))->toBe('Hydration Test Slide');
});

it('Layer C Step 4: opens View page for brand_strip and verifies nested brands repeater hydration', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    $block = ContentBlock::factory()->create([
        'type' => 'brand_strip',
        'placement' => Placement::HOME_BRAND_STRIP->value,
        'schema_version' => 1,
        'payload' => [
            'eyebrow' => 'GOOD COMPANY',
            'title' => 'Great brands.',
            'brands' => [
                ['label' => 'Apple', 'url' => '/shop?brand=Apple'],
                ['label' => 'Samsung', 'url' => '/shop?brand=Samsung'],
                ['label' => 'Sony', 'url' => '/shop?brand=Sony'],
            ],
        ],
    ]);
    if (! $block instanceof ContentBlock) {
        throw new \RuntimeException('Expected ContentBlock instance.');
    }

    $response = get('/admin/content-blocks/'.$block->public_id);
    $response->assertOk();

    $component = Livewire::test(ViewContentBlock::class, ['record' => $block->public_id]);
    $component->assertSuccessful();

    /** @var ViewContentBlock $instance */
    $instance = $component->instance();
    expect($instance->form->isDisabled())->toBeTrue()
        ->and($component->get('data.payload.eyebrow'))->toBe('GOOD COMPANY')
        ->and($component->get('data.payload.title'))->toBe('Great brands.');

    $brands = $component->get('data.payload.brands');
    expect(array_values(is_array($brands) ? $brands : []))->toBe([
        ['label' => 'Apple', 'url' => '/shop?brand=Apple'],
        ['label' => 'Samsung', 'url' => '/shop?brand=Samsung'],
        ['label' => 'Sony', 'url' => '/shop?brand=Sony'],
    ]);
});

it('Layer C Step 5: verifies ContentBlock history behavior and activity logging', function (): void {
    /** @var User $admin */
    $admin = User::factory()->platformAdmin()->create();
    actingAs($admin);

    (new ContentSeeder)->run();

    /** @var ContentBlock $block */
    $block = ContentBlock::where('placement', Placement::HOME_BRAND_STRIP->value)->firstOrFail();

    // Verify Filament does not have a separate subpage for history (returns 404)
    get('/admin/content-blocks/'.$block->public_id.'/history')
        ->assertNotFound();

    // Mutate block and verify activity log record is written
    $payload = $block->payload;
    $payload['title'] = 'Updated Brand Partners';
    app(UpdateContentBlock::class)($block->id, new UpdateContentBlockData(null, null, 0), $payload, $admin);

    $activity = Activity::where('subject_type', ContentBlock::class)
        ->where('subject_id', $block->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->description)->toBe('updated')
        ->and($activity->properties['changed'])->toContain('payload');
});

it('Layer C Step 6: edits slide sort_order and confirms homepage order changes', function (): void {
    (new ContentSeeder)->run();

    // In seeded database, slides are ordered by sort_order 0, 1, 2
    $firstSlide = ContentBlock::where('placement', Placement::HOME_HERO_CAROUSEL->value)
        ->orderBy('sort_order')
        ->firstOrFail();
    $lastSlide = ContentBlock::where('placement', Placement::HOME_HERO_CAROUSEL->value)
        ->orderByDesc('sort_order')
        ->firstOrFail();

    $initialHtml = get('/')->getContent();
    $firstTitle = (string) $firstSlide->payload['title_line_1'];
    $lastTitle = (string) $lastSlide->payload['title_line_1'];

    expect(strpos($initialHtml, $firstTitle))
        ->toBeLessThan(strpos($initialHtml, $lastTitle));

    // Edit sort order: promote lastSlide to sort_order 0 and demote firstSlide to 10
    app(UpdateContentBlock::class)(
        $lastSlide->id,
        new UpdateContentBlockData(null, null, 0),
        $lastSlide->payload
    );
    app(UpdateContentBlock::class)(
        $firstSlide->id,
        new UpdateContentBlockData(null, null, 10),
        $firstSlide->payload
    );

    $updatedHtml = get('/')->getContent();
    expect(strpos($updatedHtml, $lastTitle))
        ->toBeLessThan(strpos($updatedHtml, $firstTitle));
});
