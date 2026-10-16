<?php

declare(strict_types=1);

use App\Domain\Content\Blocks\SectionHeader\SectionHeaderData;
use Illuminate\Validation\ValidationException;

it('validates section header payload attributes', function () {
    $validData = [
        'eyebrow' => 'FIND YOUR THING',
        'title' => 'A little something for every day.',
        'cta_label' => 'Explore all categories',
        'cta_url' => '/shop',
    ];

    $dto = SectionHeaderData::validateAndCreate($validData);

    expect($dto->eyebrow)->toBe('FIND YOUR THING')
        ->and($dto->title)->toBe('A little something for every day.')
        ->and($dto->cta_label)->toBe('Explore all categories')
        ->and($dto->cta_url)->toBe('/shop');

    // Title containing HTML/script fails NoHtml
    $invalidHtml = array_merge($validData, ['title' => 'Title with <script>alert(1)</script>']);
    expect(fn () => SectionHeaderData::validateAndCreate($invalidHtml))->toThrow(ValidationException::class);

    // Optional cta_label and cta_url can be null
    $optionalDto = SectionHeaderData::validateAndCreate([
        'eyebrow' => 'FIND YOUR THING',
        'title' => 'A little something for every day.',
    ]);
    expect($optionalDto->cta_label)->toBeNull()
        ->and($optionalDto->cta_url)->toBeNull();
});
