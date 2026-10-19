<?php

declare(strict_types=1);

use App\Domain\Content\Actions\GetLayoutContent;
use App\Domain\Content\Actions\ResolveContentBlockPreview;
use App\Domain\Content\Actions\SaveContentBlockPreviewState;
use App\Domain\Content\Enums\Placement;
use App\Domain\Content\Enums\PublishStatus;
use App\Domain\Content\Models\ContentBlock;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\Cache;

it('replaces singleton placement with target block in full layout preview', function (): void {
    /** @var ContentBlock $liveBlock */
    $liveBlock = ContentBlock::factory()->create([
        'type' => 'announcement_bar',
        'placement' => Placement::ANNOUNCEMENT_BAR,
        'status' => PublishStatus::PUBLISHED,
        'is_enabled' => true,
        'payload' => ['text' => 'Live announcement', 'tone' => 'info'],
    ]);

    /** @var ContentBlock $targetBlock */
    $targetBlock = ContentBlock::factory()->create([
        'type' => 'announcement_bar',
        'placement' => Placement::ANNOUNCEMENT_BAR,
        'status' => PublishStatus::DRAFT,
        'is_enabled' => true,
        'payload' => ['text' => 'Draft announcement', 'tone' => 'warning'],
    ]);

    $action = app(ResolveContentBlockPreview::class);
    $preview = $action($targetBlock, 1, includeFullLayout: true, useEphemeralCache: false);

    expect($preview->block)->not->toBeNull()
        ->and($preview->block->id)->toBe($targetBlock->public_id)
        ->and($preview->layout)->not->toBeNull()
        ->and($preview->layout->placements['announcement_bar'])->toHaveCount(1)
        ->and($preview->layout->placements['announcement_bar'][0]->id)->toBe($targetBlock->public_id)
        ->and($preview->layout->placements['announcement_bar'][0]->data['text'])->toBe('Draft announcement');
});

it('merges and sorts multi-block placements according to Decision D8 in full layout preview', function (): void {
    $createSlide = function (int $sortOrder, string $label): ContentBlock {
        /** @var ContentBlock $block */
        $block = ContentBlock::factory()->create([
            'type' => 'promo_slide',
            'schema_version' => 1,
            'placement' => Placement::HOME_HERO_CAROUSEL,
            'status' => PublishStatus::PUBLISHED,
            'is_enabled' => true,
            'sort_order' => $sortOrder,
            'payload' => [
                'theme' => 'sky',
                'eyebrow' => 'Offer',
                'title_line_1' => $label,
                'title_line_2' => null,
                'description' => 'Description',
                'cta_label' => 'Shop',
                'cta_url' => '/shop',
                'image_url' => '/images/sample.jpg',
                'image_alt' => 'Sample',
                'label' => $label,
            ],
        ]);

        return $block;
    };

    $slide1 = $createSlide(10, 'Slide 10');
    $slide2 = $createSlide(20, 'Slide 20');
    $slide3 = $createSlide(30, 'Slide 30');

    /** @var ContentBlock $draftSlide */
    $draftSlide = ContentBlock::factory()->create([
        'type' => 'promo_slide',
        'schema_version' => 1,
        'placement' => Placement::HOME_HERO_CAROUSEL,
        'status' => PublishStatus::DRAFT,
        'is_enabled' => true,
        'sort_order' => 15,
        'payload' => [
            'theme' => 'mint',
            'eyebrow' => 'Offer',
            'title_line_1' => 'Draft Slide 15',
            'title_line_2' => null,
            'description' => 'Description',
            'cta_label' => 'Shop',
            'cta_url' => '/shop',
            'image_url' => '/images/sample.jpg',
            'image_alt' => 'Sample',
            'label' => 'Draft Slide 15',
        ],
    ]);

    $action = app(ResolveContentBlockPreview::class);
    $preview = $action($draftSlide, 1, includeFullLayout: true, useEphemeralCache: false);

    expect($preview->layout)->not->toBeNull();
    $slides = $preview->layout->placements['home_hero_carousel'];
    expect($slides)->toHaveCount(4)
        ->and($slides[0]->id)->toBe($slide1->public_id)
        ->and($slides[1]->id)->toBe($draftSlide->public_id)
        ->and($slides[2]->id)->toBe($slide2->public_id)
        ->and($slides[3]->id)->toBe($slide3->public_id);
});

it('evicts lowest ranked block when multi-block capacity is exceeded during preview', function (): void {
    $createSlide = function (int $sortOrder): ContentBlock {
        /** @var ContentBlock $block */
        $block = ContentBlock::factory()->create([
            'type' => 'promo_slide',
            'schema_version' => 1,
            'placement' => Placement::HOME_HERO_CAROUSEL,
            'status' => PublishStatus::PUBLISHED,
            'is_enabled' => true,
            'sort_order' => $sortOrder,
            'payload' => [
                'theme' => 'sky',
                'eyebrow' => 'Offer',
                'title_line_1' => "Slide {$sortOrder}",
                'title_line_2' => null,
                'description' => 'Description',
                'cta_label' => 'Shop',
                'cta_url' => '/shop',
                'image_url' => '/images/sample.jpg',
                'image_alt' => 'Sample',
                'label' => "Slide {$sortOrder}",
            ],
        ]);

        return $block;
    };

    $s1 = $createSlide(10);
    $s2 = $createSlide(20);
    $s3 = $createSlide(30);
    $s4 = $createSlide(40);
    $s5 = $createSlide(50);

    /** @var ContentBlock $draftSlide */
    $draftSlide = ContentBlock::factory()->create([
        'type' => 'promo_slide',
        'schema_version' => 1,
        'placement' => Placement::HOME_HERO_CAROUSEL,
        'status' => PublishStatus::DRAFT,
        'is_enabled' => true,
        'sort_order' => 99,
        'payload' => [
            'theme' => 'lilac',
            'eyebrow' => 'Offer',
            'title_line_1' => 'Draft Slide 99',
            'title_line_2' => null,
            'description' => 'Description',
            'cta_label' => 'Shop',
            'cta_url' => '/shop',
            'image_url' => '/images/sample.jpg',
            'image_alt' => 'Sample',
            'label' => 'Draft Slide 99',
        ],
    ]);

    $action = app(ResolveContentBlockPreview::class);
    $preview = $action($draftSlide, 1, includeFullLayout: true, useEphemeralCache: false);

    expect($preview->layout)->not->toBeNull();
    $slides = $preview->layout->placements['home_hero_carousel'];
    expect($slides)->toHaveCount(5);

    $slideIds = array_map(fn ($s) => $s->id, $slides);
    expect($slideIds)->toContain($s1->public_id)
        ->and($slideIds)->toContain($s2->public_id)
        ->and($slideIds)->toContain($s3->public_id)
        ->and($slideIds)->toContain($s4->public_id)
        ->and($slideIds)->toContain($draftSlide->public_id)
        ->and($slideIds)->not->toContain($s5->public_id);
});

it('matches GetLayoutContent payload strictly for live blocks', function (): void {
    (new ContentSeeder)->run();

    /** @var ContentBlock $liveBanner */
    $liveBanner = ContentBlock::where('placement', Placement::HOME_COLLECTION_BANNER->value)->firstOrFail();

    $previewAction = app(ResolveContentBlockPreview::class);
    $preview = $previewAction($liveBanner, 1, includeFullLayout: true, useEphemeralCache: false);

    $getLayout = app(GetLayoutContent::class);
    $liveLayout = $getLayout()->data;

    expect($preview->layout)->not->toBeNull()
        ->and($preview->layout->placements['home_collection_banner'][0]->toArray())
        ->toBe($liveLayout->placements['home_collection_banner'][0]->toArray());
});

it('flags fallback payload when cached draft fails validation', function (): void {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'announcement_bar',
        'placement' => Placement::ANNOUNCEMENT_BAR,
        'payload' => ['text' => 'Stored valid text', 'tone' => 'info'],
    ]);

    $userId = 1;
    app(SaveContentBlockPreviewState::class)($userId, $block->public_id, ['text' => '', 'tone' => 'info'], 0);

    $action = app(ResolveContentBlockPreview::class);
    $preview = $action($block, $userId, includeFullLayout: false, useEphemeralCache: true);

    expect($preview->usingFallbackPayload)->toBeTrue()
        ->and($preview->isUnservable)->toBeFalse()
        ->and($preview->block)->not->toBeNull()
        ->and($preview->block->data['text'])->toBe('Stored valid text');
});

it('renders valid cached draft over corrupt stored database payload', function (): void {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'announcement_bar',
        'placement' => Placement::ANNOUNCEMENT_BAR,
        'payload' => ['text' => '', 'invalid_shape' => true],
    ]);

    $userId = 1;
    app(SaveContentBlockPreviewState::class)($userId, $block->public_id, ['text' => 'Valid Draft Text', 'tone' => 'info'], 0);

    $action = app(ResolveContentBlockPreview::class);
    $preview = $action($block, $userId, includeFullLayout: false, useEphemeralCache: true);

    expect($preview->usingFallbackPayload)->toBeFalse()
        ->and($preview->isUnservable)->toBeFalse()
        ->and($preview->block)->not->toBeNull()
        ->and($preview->block->data['text'])->toBe('Valid Draft Text');
});

it('marks preview unservable when stored database payload is corrupt and no draft exists', function (): void {
    /** @var ContentBlock $block */
    $block = ContentBlock::factory()->create([
        'type' => 'announcement_bar',
        'placement' => Placement::ANNOUNCEMENT_BAR,
        'payload' => ['text' => '', 'invalid_shape' => true],
    ]);

    $userId = 1;
    Cache::forget(SaveContentBlockPreviewState::cacheKey($userId, $block->public_id));

    $action = app(ResolveContentBlockPreview::class);
    $preview = $action($block, $userId, includeFullLayout: false, useEphemeralCache: false);

    expect($preview->isUnservable)->toBeTrue()
        ->and($preview->usingFallbackPayload)->toBeFalse()
        ->and($preview->block)->toBeNull();
});
