<?php

declare(strict_types=1);

use App\Domain\Content\Blocks\FeatureBanner\FeatureBannerData;
use Illuminate\Validation\ValidationException;

it('validates feature banner payload attributes', function () {
    $validData = [
        'eyebrow' => 'SOUND',
        'title_line_1' => 'Big sound.',
        'title_line_2' => null,
        'description' => null,
        'cta_label' => 'Explore',
        'cta_url' => '/shop?category=speakers',
        'image_url' => '/images/products/marshall-acton-iii.png',
        'image_alt' => 'Marshall speaker',
        'badge_text' => 'MARSHALL ACTON III',
    ];

    $dto = FeatureBannerData::validateAndCreate($validData);

    expect($dto->eyebrow)->toBe('SOUND')
        ->and($dto->title_line_1)->toBe('Big sound.')
        ->and($dto->title_line_2)->toBeNull()
        ->and($dto->description)->toBeNull()
        ->and($dto->cta_label)->toBe('Explore')
        ->and($dto->cta_url)->toBe('/shop?category=speakers')
        ->and($dto->image_url)->toBe('/images/products/marshall-acton-iii.png')
        ->and($dto->image_alt)->toBe('Marshall speaker')
        ->and($dto->badge_text)->toBe('MARSHALL ACTON III');

    // badge_text exceeds 40 chars
    $invalidBadge = array_merge($validData, ['badge_text' => str_repeat('a', 41)]);
    expect(fn () => FeatureBannerData::validateAndCreate($invalidBadge))->toThrow(ValidationException::class);
});
