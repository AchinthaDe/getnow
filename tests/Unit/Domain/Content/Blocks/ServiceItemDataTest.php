<?php

declare(strict_types=1);

use App\Domain\Content\Blocks\ServiceItem\ServiceItemData;
use App\Domain\Content\Enums\ServiceIcon;
use Illuminate\Validation\ValidationException;

it('validates service item ServiceIcon enum and link safety', function () {
    $validData = [
        'icon' => 'truck',
        'title' => 'Islandwide delivery',
        'subtitle' => 'From our store to your door',
        'link_url' => '/help',
    ];

    $dto = ServiceItemData::validateAndCreate($validData);

    expect($dto->icon)->toBe(ServiceIcon::TRUCK)
        ->and($dto->title)->toBe('Islandwide delivery')
        ->and($dto->subtitle)->toBe('From our store to your door')
        ->and($dto->link_url)->toBe('/help');

    // Invalid enum value
    $invalidIcon = array_merge($validData, ['icon' => 'nonexistent_icon']);
    expect(fn () => ServiceItemData::validateAndCreate($invalidIcon))->toThrow(ValidationException::class);

    // Insecure/invalid link scheme
    $invalidLink = array_merge($validData, ['link_url' => 'http://insecure.com']);
    expect(fn () => ServiceItemData::validateAndCreate($invalidLink))->toThrow(ValidationException::class);

    // Null link_url is valid
    $nullLink = array_merge($validData, ['link_url' => null]);
    $nullDto = ServiceItemData::validateAndCreate($nullLink);
    expect($nullDto->link_url)->toBeNull();
});
