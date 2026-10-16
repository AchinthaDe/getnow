<?php

declare(strict_types=1);

use App\Domain\Content\Blocks\BrandStrip\BrandStripData;
use App\Domain\Content\Blocks\BrandStrip\BrandStripItemData;
use Illuminate\Validation\ValidationException;

it('validates brand strip item collection constraints and normalizes array keys', function () {
    $validData = [
        'eyebrow' => 'GOOD COMPANY',
        'title' => 'Great brands.',
        'brands' => [
            'uuid-key-1' => ['label' => 'Apple', 'url' => '/shop?brand=Apple'],
            'uuid-key-2' => ['label' => 'Samsung', 'url' => 'HTTPS://shop.com/Samsung'],
        ],
    ];

    $dto = BrandStripData::validateAndCreate($validData);

    expect($dto->eyebrow)->toBe('GOOD COMPANY')
        ->and($dto->title)->toBe('Great brands.')
        ->and($dto->brands)->toHaveCount(2)
        ->and(array_is_list($dto->brands))->toBeTrue()
        ->and($dto->brands[0])->toBeInstanceOf(BrandStripItemData::class)
        ->and($dto->brands[0]->label)->toBe('Apple')
        ->and($dto->brands[0]->url)->toBe('/shop?brand=Apple')
        ->and($dto->brands[1]->url)->toBe('https://shop.com/Samsung');

    // More than 10 brands exceeds Max(10) constraint
    $tooManyBrands = [
        'eyebrow' => 'GOOD COMPANY',
        'title' => 'Great brands.',
        'brands' => array_map(fn (int $i) => ['label' => "Brand {$i}", 'url' => "/shop/{$i}"], range(1, 11)),
    ];
    expect(fn () => BrandStripData::validateAndCreate($tooManyBrands))->toThrow(ValidationException::class);
});
