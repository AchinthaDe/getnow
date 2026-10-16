<?php

declare(strict_types=1);

use App\Domain\Content\Blocks\PromoSlide\PromoSlideData;
use App\Domain\Content\Enums\PromoTheme;
use Illuminate\Validation\ValidationException;

it('validates promo slide payload attributes and link formats', function () {
    $validData = [
        'theme' => 'sky',
        'eyebrow' => 'FIND YOUR FREQUENCY',
        'title_line_1' => 'Less noise.',
        'title_line_2' => 'More you.',
        'description' => 'Great audio.',
        'cta_label' => 'Shop audio',
        'cta_url' => '/shop?category=audio',
        'image_url' => '/images/products/soundcore-space-2.jpg',
        'image_alt' => 'Soundcore headphones',
        'label' => 'Audio offers',
    ];

    $dto = PromoSlideData::validateAndCreate($validData);

    expect($dto->theme)->toBe(PromoTheme::SKY)
        ->and($dto->eyebrow)->toBe('FIND YOUR FREQUENCY')
        ->and($dto->title_line_1)->toBe('Less noise.')
        ->and($dto->title_line_2)->toBe('More you.')
        ->and($dto->description)->toBe('Great audio.')
        ->and($dto->cta_label)->toBe('Shop audio')
        ->and($dto->cta_url)->toBe('/shop?category=audio')
        ->and($dto->image_url)->toBe('/images/products/soundcore-space-2.jpg')
        ->and($dto->image_alt)->toBe('Soundcore headphones')
        ->and($dto->label)->toBe('Audio offers');

    // SafeLink violation on cta_url
    $invalidCta = array_merge($validData, ['cta_url' => 'javascript:alert(1)']);
    expect(fn () => PromoSlideData::validateAndCreate($invalidCta))->toThrow(ValidationException::class);

    // SafeLink violation on image_url
    $invalidImage = array_merge($validData, ['image_url' => 'http://insecure.com/pic.jpg']);
    expect(fn () => PromoSlideData::validateAndCreate($invalidImage))->toThrow(ValidationException::class);

    // NoHtml violation on title_line_1
    $invalidHtml = array_merge($validData, ['title_line_1' => '<b>Less noise</b>']);
    expect(fn () => PromoSlideData::validateAndCreate($invalidHtml))->toThrow(ValidationException::class);
});
